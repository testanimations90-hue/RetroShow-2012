<?php

const RECS_DENSE_DIM = 32;

function recs_terms_from_text(string $text): array {
    $text = mb_strtolower(trim($text), 'UTF-8');
    if ($text === '') return [];
    $parts = preg_split('/[^\p{L}\p{N}_-]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts)) return [];
    $stop = [
        'и'=>1,'в'=>1,'на'=>1,'по'=>1,'с'=>1,'для'=>1,'что'=>1,'как'=>1,'это'=>1,'или'=>1,'а'=>1,'но'=>1,
        'the'=>1,'and'=>1,'for'=>1,'with'=>1,'from'=>1,'this'=>1,'that'=>1,'you'=>1,'your'=>1,'are'=>1
    ];
    $out = [];
    foreach ($parts as $p) {
        if (mb_strlen($p, 'UTF-8') < 2) continue;
        if (isset($stop[$p])) continue;
        $out[] = $p;
    }
    return $out;
}

function recs_vec_norm(array $v): float {
    $s = 0.0;
    foreach ($v as $x) $s += ((float)$x * (float)$x);
    return $s > 0.0 ? sqrt($s) : 0.0;
}

function recs_vec_cosine(array $a, float $na, array $b, float $nb): float {
    if ($na <= 0.0 || $nb <= 0.0 || $a === [] || $b === []) return 0.0;
    if (count($a) > count($b)) { $t = $a; $a = $b; $b = $t; }
    $dot = 0.0;
    foreach ($a as $k => $x) {
        if (!isset($b[$k])) continue;
        $dot += ((float)$x * (float)$b[$k]);
    }
    return $dot > 0.0 ? ($dot / ($na * $nb)) : 0.0;
}

function recs_hash_u32(string $s): int {
    $h = crc32($s);
    if ($h < 0) $h += 4294967296;
    return (int)$h;
}

function recs_dense_from_terms(array $terms): array {
    if ($terms === []) return array_fill(0, RECS_DENSE_DIM, 0.0);
    $bucketCount = 256;
    $hashed = array_fill(0, $bucketCount, 0.0);
    foreach ($terms as $t) {
        $h = recs_hash_u32($t);
        $idx = $h % $bucketCount;
        $sgn = (($h >> 1) & 1) ? 1.0 : -1.0;
        $hashed[$idx] += $sgn;
    }
    $out = array_fill(0, RECS_DENSE_DIM, 0.0);
    for ($d = 0; $d < RECS_DENSE_DIM; $d++) {
        $acc = 0.0;
        for ($i = 0; $i < $bucketCount; $i++) {
            $r = recs_hash_u32('rp:' . $d . ':' . $i);
            $proj = (($r & 1) === 1) ? 1.0 : -1.0;
            $acc += ($hashed[$i] * $proj);
        }
        $out[$d] = $acc;
    }
    $n = recs_vec_norm($out);
    if ($n > 0.0) {
        for ($i = 0; $i < RECS_DENSE_DIM; $i++) $out[$i] /= $n;
    }
    return $out;
}

function recs_dense_cosine(array $a, array $b): float {
    if ($a === [] || $b === [] || count($a) !== count($b)) return 0.0;
    $dot = 0.0; $na = 0.0; $nb = 0.0;
    $n = count($a);
    for ($i = 0; $i < $n; $i++) {
        $x = (float)$a[$i]; $y = (float)$b[$i];
        $dot += ($x * $y); $na += ($x * $x); $nb += ($y * $y);
    }
    if ($na <= 0.0 || $nb <= 0.0) return 0.0;
    return $dot / (sqrt($na) * sqrt($nb));
}

function recs_days_since(int $ts, int $now): float {
    if ($ts <= 0) return 3650.0;
    $d = ($now - $ts) / 86400.0;
    return $d < 0 ? 0.0 : $d;
}

function recs_freshness_weight(float $days): float {
    if ($days <= 1.0) return 1.00;
    if ($days <= 3.0) return 0.92;
    if ($days <= 7.0) return 0.82;
    if ($days <= 14.0) return 0.72;
    if ($days <= 30.0) return 0.60;
    if ($days <= 90.0) return 0.46;
    return 0.34;
}

function recs_tf_vector(array $terms): array {
    $tf = [];
    foreach ($terms as $t) $tf[$t] = (($tf[$t] ?? 0.0) + 1.0);
    $maxTf = 0.0;
    foreach ($tf as $v) if ($v > $maxTf) $maxTf = $v;
    if ($maxTf > 0.0) {
        foreach ($tf as $k => $v) $tf[$k] = 0.5 + (0.5 * ((float)$v / $maxTf));
    }
    return $tf;
}

function recs_terms_for_video_row(array $row): array {
    $author = mb_strtolower(trim((string)($row['user'] ?? '')), 'UTF-8');
    $authorToken = $author !== '' ? ('author_' . $author) : '';
    return recs_terms_from_text(
        $authorToken . ' ' .
        (string)($row['tags'] ?? '') . ' ' .
        (string)($row['title'] ?? '') . ' ' .
        (string)($row['description'] ?? '') . ' ' .
        (string)($row['category'] ?? '') . ' ' .
        (string)($row['language'] ?? '')
    );
}

function recs_vector_signature(array $row): string {
    return sha1(
        trim((string)($row['title'] ?? '')) . '|' .
        trim((string)($row['description'] ?? '')) . '|' .
        trim((string)($row['tags'] ?? '')) . '|' .
        trim((string)($row['user'] ?? '')) . '|' .
        trim((string)($row['category'] ?? '')) . '|' .
        trim((string)($row['language'] ?? ''))
    );
}

function recs_cache_ensure(PDO $db): void {
    static $done = false;
    if ($done) return;
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS rec_video_vectors (
            video_id INTEGER PRIMARY KEY,
            sig TEXT NOT NULL,
            tf_json TEXT NOT NULL,
            dense_json TEXT NOT NULL,
            updated_at INTEGER NOT NULL
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_rec_video_vectors_updated ON rec_video_vectors (updated_at DESC)");
    } catch (Exception $e) {
    }
    $done = true;
}

function recs_build_or_load_cached_vectors(PDO $db, array $rows): array {
    recs_cache_ensure($db);
    $byId = [];
    $ids = [];
    foreach ($rows as $r) {
        $id = (int)($r['id'] ?? 0);
        if ($id <= 0) continue;
        $byId[$id] = $r;
        $ids[] = $id;
    }
    if ($ids === []) return [];

    $cached = [];
    try {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $db->prepare("SELECT video_id, sig, tf_json, dense_json FROM rec_video_vectors WHERE video_id IN ($ph)");
        $st->execute($ids);
        foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
            $vid = (int)($r['video_id'] ?? 0);
            if ($vid > 0) $cached[$vid] = $r;
        }
    } catch (Exception $e) {
    }

    $out = [];
    $upserts = [];
    $now = time();
    foreach ($byId as $vid => $row) {
        $sig = recs_vector_signature($row);
        $use = isset($cached[$vid]) && ((string)$cached[$vid]['sig'] === $sig);
        if ($use) {
            $tf = json_decode((string)($cached[$vid]['tf_json'] ?? '{}'), true);
            $dense = json_decode((string)($cached[$vid]['dense_json'] ?? '[]'), true);
            if (!is_array($tf) || !is_array($dense) || count($dense) !== RECS_DENSE_DIM) $use = false;
        }
        if (!$use) {
            $terms = recs_terms_for_video_row($row);
            $tf = recs_tf_vector($terms);
            $dense = recs_dense_from_terms($terms);
            $upserts[] = [
                'video_id' => $vid,
                'sig' => $sig,
                'tf_json' => json_encode($tf),
                'dense_json' => json_encode($dense),
                'updated_at' => $now
            ];
        }
        $out[$vid] = ['tf' => $tf, 'dense' => $dense];
    }

    if ($upserts !== []) {
        try {
            $st = $db->prepare("INSERT INTO rec_video_vectors (video_id, sig, tf_json, dense_json, updated_at)
                VALUES (?, ?, ?, ?, ?)
                ON CONFLICT(video_id) DO UPDATE SET
                    sig = excluded.sig,
                    tf_json = excluded.tf_json,
                    dense_json = excluded.dense_json,
                    updated_at = excluded.updated_at");
            foreach ($upserts as $u) {
                $st->execute([$u['video_id'], $u['sig'], $u['tf_json'], $u['dense_json'], $u['updated_at']]);
            }
        } catch (Exception $e) {
        }
    }

    return $out;
}

function recs_build_idf(array $rows): array {
    $df = [];
    $docs = 0;
    foreach ($rows as $r) {
        $terms = recs_terms_for_video_row($r);
        if ($terms === []) continue;
        $docs++;
        $uniq = [];
        foreach ($terms as $t) $uniq[$t] = true;
        foreach ($uniq as $t => $_) $df[$t] = (($df[$t] ?? 0) + 1);
    }
    if ($docs <= 0) return [];
    $idf = [];
    foreach ($df as $t => $d) $idf[$t] = log((float)$docs / (1.0 + (float)$d));
    return $idf;
}

function recs_apply_idf(array $tf, array $idf): array {
    if ($tf === []) return [];
    $v = [];
    foreach ($tf as $t => $w) {
        $idfw = (float)($idf[$t] ?? 0.0);
        if ($idfw <= 0.0) continue;
        $v[$t] = (float)$w * $idfw;
    }
    return $v;
}

function recs_video_views_has_column(PDO $db, string $name): bool {
    static $cols = null;
    if ($cols === null) {
        $cols = [];
        try {
            $rows = $db->query("PRAGMA table_info(video_views)")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) $cols[(string)($r['name'] ?? '')] = true;
        } catch (Exception $e) {
        }
    }
    return isset($cols[$name]);
}

function recs_get_home_recommendations(PDO $db, ?string $viewerUser, string $viewerIp, int $limit = 12): array {
    $limit = max(1, $limit);
    $now = time();

    try {
        $rows = $db->query("SELECT id, public_id, title, description, file, preview, user, tags, views, time
            FROM videos
            WHERE (private = 0 OR private IS NULL) AND " . visible_video_sql_condition('videos', 'user') . "
            ORDER BY id DESC
            LIMIT 1800")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        return [];
    }
    if ($rows === []) return [];
    $rowMap = [];
    foreach ($rows as $r) {
        $vid = (int)($r['id'] ?? 0);
        if ($vid > 0) $rowMap[$vid] = $r;
    }

    $vecMap = recs_build_or_load_cached_vectors($db, $rows);
    $idf = recs_build_idf($rows);

    $userTfWeighted = [];
    $userDense = array_fill(0, RECS_DENSE_DIM, 0.0);
    $userWeightSum = 0.0;
    $seen = [];
    $userAuthors = [];
    $sessionTfWeighted = [];
    $sessionAuthors = [];
    $profileEvents = 0;

    $hasWatchSeconds = recs_video_views_has_column($db, 'watch_seconds');
    $hasDuration = recs_video_views_has_column($db, 'video_duration');
    $hasCompletion = recs_video_views_has_column($db, 'completion_percent');

    try {
        $retSelect = $hasCompletion
            ? "AVG(COALESCE(vv.completion_percent, 0.0)) AS completion_avg"
            : (($hasWatchSeconds && $hasDuration)
                ? "AVG(CASE WHEN COALESCE(vv.video_duration,0)>0 THEN (COALESCE(vv.watch_seconds,0.0)*100.0)/vv.video_duration ELSE 0.0 END) AS completion_avg"
                : "0.0 AS completion_avg");

        if ($viewerUser) {
            $st = $db->prepare("SELECT vv.video_id, COUNT(*) AS c, MAX(vv.viewed_at) AS last_viewed, $retSelect
                FROM video_views vv
                JOIN videos v ON v.id = vv.video_id
                WHERE vv.user = ? AND (v.private = 0 OR v.private IS NULL)
                GROUP BY vv.video_id
                ORDER BY last_viewed DESC
                LIMIT 120");
            $st->execute([$viewerUser]);
        } else {
            $st = $db->prepare("SELECT vv.video_id, COUNT(*) AS c, MAX(vv.viewed_at) AS last_viewed, $retSelect
                FROM video_views vv
                JOIN videos v ON v.id = vv.video_id
                WHERE vv.ip = ? AND (v.private = 0 OR v.private IS NULL)
                GROUP BY vv.video_id
                ORDER BY last_viewed DESC
                LIMIT 300");
            $st->execute([$viewerIp]);
        }
        $hist = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($hist as $hIdx => $h) {
            $vid = (int)($h['video_id'] ?? 0);
            if ($vid <= 0 || !isset($vecMap[$vid])) continue;
            $seen[$vid] = true;
            $profileEvents++;

            $repeat = max(1, (int)($h['c'] ?? 1));
            $last = (int)($h['last_viewed'] ?? 0);
            $days = recs_days_since($last, $now);
            $recency = exp(-$days / 3.0);
            $repeatFactor = min(1.0, log(1.0 + $repeat) / log(8.0));
            $completion = min(1.0, max(0.0, ((float)($h['completion_avg'] ?? 0.0)) / 100.0));
            $weight = ($completion * 0.70) + ($repeatFactor * 0.20) + ($recency * 0.10);
            if ($weight <= 0.0) continue;

            foreach ((array)$vecMap[$vid]['tf'] as $t => $w) {
                $userTfWeighted[$t] = (($userTfWeighted[$t] ?? 0.0) + ((float)$w * $weight));
            }
            $author = mb_strtolower(trim((string)($rowMap[$vid]['user'] ?? '')), 'UTF-8');
            if ($author !== '') {
                $userAuthors[$author] = ($userAuthors[$author] ?? 0.0) + $weight;
            }

            if ($hIdx < 5) {
                $sessionW = $weight * (1.25 - ($hIdx * 0.10));
                foreach ((array)$vecMap[$vid]['tf'] as $t => $w) {
                    $sessionTfWeighted[$t] = (($sessionTfWeighted[$t] ?? 0.0) + ((float)$w * $sessionW));
                }
                if ($author !== '') {
                    $sessionAuthors[$author] = ($sessionAuthors[$author] ?? 0.0) + $sessionW;
                }
            }
            $d = (array)$vecMap[$vid]['dense'];
            for ($i = 0; $i < RECS_DENSE_DIM; $i++) $userDense[$i] += ((float)$d[$i] * $weight);
            $userWeightSum += $weight;
        }
    } catch (Exception $e) {
    }

    $liked = [];
    $disliked = [];
    try {
        if ($viewerUser) {
            $stFav = $db->prepare("SELECT video_id FROM user_favourites WHERE user = ? ORDER BY created_at DESC LIMIT 300");
            $stFav->execute([$viewerUser]);
            foreach (($stFav->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) $liked[(int)($r['video_id'] ?? 0)] = true;
            $stRate = $db->prepare("SELECT video_id, rating FROM ratings WHERE user = ? ORDER BY rated_at DESC LIMIT 350");
            $stRate->execute([$viewerUser]);
        } else {
            $stRate = $db->prepare("SELECT video_id, rating FROM ratings WHERE ip = ? ORDER BY rated_at DESC LIMIT 220");
            $stRate->execute([$viewerIp]);
        }
        foreach (($stRate->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
            $vid = (int)($r['video_id'] ?? 0);
            $rt = (int)($r['rating'] ?? 0);
            if ($vid <= 0) continue;
            if ($rt >= 4) $liked[$vid] = true;
            if ($rt <= 2) $disliked[$vid] = true;
        }
    } catch (Exception $e) {
    }

    $trend = [];
    try {
        $cut = $now - (7 * 86400);
        $stTr = $db->prepare("SELECT video_id, COUNT(*) AS c FROM video_views WHERE viewed_at >= ? GROUP BY video_id ORDER BY c DESC LIMIT 3000");
        $stTr->execute([$cut]);
        foreach (($stTr->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
            $vid = (int)($r['video_id'] ?? 0);
            if ($vid > 0) $trend[$vid] = log(1.0 + max(0, (int)($r['c'] ?? 0)));
        }
    } catch (Exception $e) {
    }

    $userSparse = recs_apply_idf($userTfWeighted, $idf);
    $uNorm = recs_vec_norm($userSparse);
    $sessionSparse = recs_apply_idf($sessionTfWeighted, $idf);
    $sessionNorm = recs_vec_norm($sessionSparse);
    $sessionTopTerms = [];
    if ($sessionTfWeighted !== []) {
        arsort($sessionTfWeighted);
        $sessionTopTerms = array_slice($sessionTfWeighted, 0, 16, true);
    }
    if ($userWeightSum > 0.0) {
        for ($i = 0; $i < RECS_DENSE_DIM; $i++) $userDense[$i] /= $userWeightSum;
    }

    $maxTrend = 1.0;
    foreach ($trend as $tv) if ((float)$tv > $maxTrend) $maxTrend = (float)$tv;
    $maxPopular = 1.0;
    foreach ($rows as $r) {
        $p = log(1.0 + max(0, (int)($r['views'] ?? 0)));
        if ($p > $maxPopular) $maxPopular = $p;
    }

    $stage1 = [];
    
    foreach ($rows as $row) {
        $vid = (int)($row['id'] ?? 0);
        if ($vid <= 0 || !isset($vecMap[$vid])) continue;
        $vs = recs_apply_idf((array)$vecMap[$vid]['tf'], $idf);
        $vn = recs_vec_norm($vs);
        $simSparse = recs_vec_cosine($vs, $vn, $userSparse, $uNorm);
        $ts = strtotime((string)($row['time'] ?? ''));
        $days = recs_days_since($ts !== false ? (int)$ts : 0, $now);
        $freshness = recs_freshness_weight($days);
        $trendNorm = min(1.0, log(1.0 + ($trend[$vid] ?? 0.0)) / log(1.0 + $maxTrend));
        $views = (int)($row['views'] ?? 0);
        $popNorm = min(1.0, log(1.0 + $views) / log(1.0 + $maxPopular));
        $popNorm = sqrt($popNorm);

        $popDecay = max(0.30, pow(exp(-$days / 55.0), 0.70));
        $popNorm *= $popDecay;
        $exploreBoost = 0.0;
        if ($views < 20) $exploreBoost += 0.05;
        if ($days <= 7.0) $exploreBoost += 0.03;
        $s1 = ($uNorm > 0.0)
            ? (($simSparse * 0.68) + ($trendNorm * 0.14) + ($freshness * 0.14) + ($popNorm * 0.04) + $exploreBoost)
            : (($popNorm * 0.54) + ($trendNorm * 0.23) + ($freshness * 0.23) + $exploreBoost);
        $row['_vs'] = $vs;
        $row['_vn'] = $vn;
        $row['_s1'] = $s1;
        $stage1[] = $row;
    }
    usort($stage1, static function (array $a, array $b): int {
        return ((float)($b['_s1'] ?? 0.0) <=> (float)($a['_s1'] ?? 0.0));
    });

    $candidateCount = min(count($stage1), max($limit * 24, 260));
    $candidates = array_slice($stage1, 0, $candidateCount);

    $scored = [];
    foreach ($candidates as $row) {
        $vid = (int)($row['id'] ?? 0);
        if ($vid <= 0 || !isset($vecMap[$vid])) continue;
        $vs = (array)($row['_vs'] ?? []);
        $vn = (float)($row['_vn'] ?? 0.0);
        $simSparse = recs_vec_cosine($vs, $vn, $userSparse, $uNorm);
        $simDense = recs_dense_cosine((array)$vecMap[$vid]['dense'], $userDense);
        $cos = ($simSparse * 0.58) + ($simDense * 0.42);
        $ts = strtotime((string)($row['time'] ?? ''));
        $days = recs_days_since($ts !== false ? (int)$ts : 0, $now);
        $freshness = recs_freshness_weight($days);
        $trendNorm = min(1.0, ((float)($trend[$vid] ?? 0.0) / $maxTrend));
        $popNorm = min(1.0, (log(1.0 + max(0, (int)($row['views'] ?? 0))) / $maxPopular)) * max(0.30, exp(-$days / 55.0));
        if ($uNorm <= 0.0) $cos = min(1.0, ($popNorm * 0.55) + ($freshness * 0.45));

        $boost = 0.0;
        if (isset($liked[$vid])) $boost += 0.20;
        if (isset($disliked[$vid])) $boost -= 0.20;
        if (isset($seen[$vid])) $boost -= 0.03;
        $authorBoost = 0.0;
        $author = mb_strtolower(trim((string)($row['user'] ?? '')), 'UTF-8');
        if ($author !== '' && isset($userAuthors[$author])) {
            $authorBoost = min(0.35, ((float)$userAuthors[$author]) * 0.08);
        }
        $sessionBoost = 0.0;
        if ($sessionNorm > 0.0) {
            $sessionSim = recs_vec_cosine($vs, $vn, $sessionSparse, $sessionNorm);
            $sessionBoost += min(0.22, $sessionSim * 0.22);
        }

        $sessionTagBoost = 0.0;
        if ($sessionTopTerms !== []) {
            $tfVideoRaw = (array)($vecMap[$vid]['tf'] ?? []);
            $tagHit = 0.0;
            foreach ($sessionTopTerms as $t => $sw) {
                if (!isset($tfVideoRaw[$t])) continue;
                $tagHit += ((float)$sw * (float)$tfVideoRaw[$t]);
            }
            $sessionTagBoost = min(0.28, (1.0 - (1.0 / (1.0 + max(0.0, $tagHit)))) * 0.28);
            $sessionBoost += $sessionTagBoost;
        }
        if ($author !== '' && isset($sessionAuthors[$author])) {
            $sessionBoost += min(0.16, ((float)$sessionAuthors[$author]) * 0.10);
        }
        $views = (int)($row['views'] ?? 0);
        $coldBoost = 0.0;
        if ($views < 20) $coldBoost += 0.05;
        if ($days <= 7.0) $coldBoost += 0.03;

        $final = ($cos * 0.66) + ($trendNorm * 0.17) + ($freshness * 0.17) + ($boost * 0.60) + ($popNorm * 0.08) + $authorBoost + $sessionBoost + $coldBoost;
        $final += (mt_rand(0, 10) / 1000.0) * (1.0 - $cos);
        $row['_s'] = $final;
        unset($row['_vs'], $row['_vn'], $row['_s1']);
        $scored[] = $row;
    }
    usort($scored, static function (array $a, array $b): int {
        $sa = (float)($a['_s'] ?? 0.0);
        $sb = (float)($b['_s'] ?? 0.0);
        if ($sa === $sb) return ((int)($b['views'] ?? 0) <=> (int)($a['views'] ?? 0));
        return ($sb <=> $sa);
    });

    $profileStrength = min(1.0, (max(0, $profileEvents) / 60.0) + (max(0.0, $uNorm) / 6.0));
    $exploreRate = max(0.05, 0.30 - ($profileStrength * 0.20));

    $top = array_slice($scored, 0, max($limit * 14, 180));
    $out = [];
    $used = [];
    $authorCount = [];
    $topicCount = [];
    $i = 0;
    while (count($out) < $limit && $top !== [] && $i < 5000) {
        $i++;
        $countTop = count($top);
        $ridx = 0;
        if ($countTop > 1) {
            if ((mt_rand(0, 10000) / 10000.0) < $exploreRate) {
                $ridx = mt_rand(0, $countTop - 1);
            } else {
                $u = mt_rand(0, 10000) / 10000.0;
                $ridx = (int)floor(($u * $u) * $countTop);
                if ($ridx < 0) $ridx = 0;
                if ($ridx >= $countTop) $ridx = $countTop - 1;
            }
        }
        $pick = $top[$ridx] ?? null;
        if ($countTop > 0) array_splice($top, $ridx, 1);
        if (!is_array($pick)) continue;

        $vid = (int)($pick['id'] ?? 0);
        if ($vid <= 0 || isset($used[$vid])) continue;
        $ak = mb_strtolower(trim((string)($pick['user'] ?? '')), 'UTF-8');
        $ac = (int)($authorCount[$ak] ?? 0);
        if ($ak !== '' && $ac >= 2 && mt_rand(0, 99) < 75) continue;
        $topicKey = '';
        $tagTerms = recs_terms_from_text((string)($pick['tags'] ?? ''));
        if ($tagTerms !== []) $topicKey = (string)$tagTerms[0];
        if ($topicKey !== '') {
            $tc = (int)($topicCount[$topicKey] ?? 0);
            if ($tc >= 4 && mt_rand(0, 99) < 35) continue;
        }

        $used[$vid] = true;
        if ($ak !== '') $authorCount[$ak] = $ac + 1;
        if ($topicKey !== '') $topicCount[$topicKey] = ((int)($topicCount[$topicKey] ?? 0)) + 1;
        unset($pick['_s']);
        $out[] = $pick;
    }

    if (count($out) < $limit) {
        foreach ($rows as $row) {
            if (count($out) >= $limit) break;
            $vid = (int)($row['id'] ?? 0);
            if ($vid <= 0 || isset($used[$vid])) continue;
            $used[$vid] = true;
            $out[] = $row;
        }
    }

    if (count($out) > $limit) $out = array_slice($out, 0, $limit);
    return $out;
}

