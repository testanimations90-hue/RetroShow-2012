<?php
header('Content-Type: application/rss+xml; charset=utf-8');

require_once __DIR__ . '/init.php';

function rss_absolute_base(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scheme = $https ? 'https' : 'http';
    $script = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/rss.php')));
    if ($script === '/' || $script === '.' || $script === '') {
        $prefix = '';
    } else {
        $prefix = rtrim($script, '/');
    }
    return $scheme . '://' . $host . $prefix;
}

function rss_video_page_url(array $video): string {
    $base = rss_absolute_base();
    $id = rawurlencode((string)($video['public_id'] ?? $video['id']));
    return $base . '/video.php?id=' . $id;
}

$base = rss_absolute_base();
$tagParam = isset($_GET['tag']) ? trim((string)$_GET['tag']) : '';
$userParam = isset($_GET['user']) ? trim((string)$_GET['user']) : '';

if ($userParam !== '') {
    $stmt = $db->prepare(
        'SELECT * FROM videos WHERE user = ? AND (private = 0 OR private IS NULL) AND ' .
        visible_video_sql_condition('videos', 'user') .
        ' ORDER BY id DESC LIMIT 15'
    );
    $stmt->execute([$userParam]);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $channel_title = 'RetroShow - канал ' . $userParam;
    $channel_desc = 'Последние публичные видео канала ' . $userParam . '.';
    $channel_link = $base . '/channel.php?user=' . rawurlencode($userParam);
} elseif ($tagParam !== '') {
    $needle = mb_strtolower($tagParam, 'UTF-8');
    $needle_words = preg_split('/\s+/', $needle, -1, PREG_SPLIT_NO_EMPTY);

    $stmt = $db->prepare(
        'SELECT * FROM videos WHERE (private = 0 OR private IS NULL) AND ' .
        visible_video_sql_condition('videos', 'user') .
        ' ORDER BY id DESC LIMIT 1500'
    );
    $stmt->execute();
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $videos = [];
    foreach ($candidates as $row) {
        $tags = isset($row['tags']) ? (string)$row['tags'] : '';
        if ($tags === '' || empty($needle_words)) {
            continue;
        }
        $tags_lc = mb_strtolower($tags, 'UTF-8');
        $score = 0;
        foreach ($needle_words as $w) {
            if ($w === '') {
                continue;
            }
            if (mb_stripos($tags_lc, $w, 0, 'UTF-8') !== false) {
                $score++;
            }
        }
        if ($score > 0) {
            $videos[] = $row;
            if (count($videos) >= 30) {
                break;
            }
        }
    }

    $channel_title = 'RetroShow - видео по тегу «' . $tagParam . '»';
    $channel_desc = 'Видео с тегом «' . $tagParam . '».';
    $channel_link = $base . '/results.php?search_type=tag&search_query=' . rawurlencode($tagParam);
} else {
    $stmt = $db->prepare(
        'SELECT * FROM videos WHERE (private = 0 OR private IS NULL) AND ' .
        visible_video_sql_condition('videos', 'user') .
        ' ORDER BY id DESC LIMIT 15'
    );
    $stmt->execute();
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $channel_title = 'RetroShow — последние видео';
    $channel_desc = 'Последние загруженные видеоролики на RetroShow.';
    $channel_link = $base . '/';
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0">
<channel>
<title><?= htmlspecialchars($channel_title, ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></title>
<description><?= htmlspecialchars($channel_desc, ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></description>
<link><?= htmlspecialchars($channel_link, ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></link>
<language>ru-RU</language>
<generator>RetroShow</generator>
<lastBuildDate><?= htmlspecialchars(date(DATE_RSS), ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></lastBuildDate>

<?php foreach ($videos as $video): ?>
<?php
    $item_link = rss_video_page_url($video);
    $ts = @strtotime((string)($video['time'] ?? ''));
    if ($ts === false) {
        $ts = time();
    }
?>
<item>
<title><?= htmlspecialchars((string)($video['title'] ?? ''), ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></title>
<description><?= htmlspecialchars((string)($video['description'] ?? ''), ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></description>
<author><?= htmlspecialchars((string)($video['user'] ?? ''), ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></author>
<pubDate><?= htmlspecialchars(date(DATE_RSS, $ts), ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></pubDate>
<link><?= htmlspecialchars($item_link, ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></link>
<guid><?= htmlspecialchars($item_link, ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></guid>
</item>
<?php endforeach; ?>

</channel>
</rss>
