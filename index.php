<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
include("init.php");

if (isset($_GET['v']) && (string)$_GET['v'] !== '') {
    $v = trim((string)$_GET['v']);
    if (preg_match('/^[A-Za-z0-9_-]{6,20}$/', $v)) {
        header('Location: video.php?id=' . rawurlencode($v), true, 302);
        exit;
    }
    header('Location: index.php', true, 302);
    exit;
}

include("template.php");
require_once __DIR__ . '/duration_helper.php';
require_once __DIR__ . '/recs.php';

$contest = false;
$show_recs_block = true;

if (isset($_SESSION['user'])) {
    $current_user = $_SESSION['user'];
    $home_block_type = 'recent_added';
    $account_email = '';
    $account_videos_watched = 0;
    $account_videos_count = 0;
    $account_favourites_count = 0;
    $account_fans_count = 0;
    $account_friends_videos_count = 0;
    $account_friends_favourites_count = 0;
    $account_unread_mail = 0;
    $account_mail_icon = 'img/mail.gif';

    $stmt = $db->prepare("SELECT SUM(views) FROM videos WHERE user = ?");
    $stmt->execute([$current_user]);
    $video_views = $stmt->fetchColumn() ?: 0;

    $stmt = $db->prepare("SELECT profile_viewed FROM user_stats WHERE user = ?");
    $stmt->execute([$current_user]);
    $channel_views = $stmt->fetchColumn() ?: 0;

    $friends_count = 0;
    $subscribers_count = 0;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_friends WHERE user = ?");
        $stmt->execute([$current_user]);
        $friends_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $friends_count = 0;
    }
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM user_friends uf
            WHERE uf.friend = ?
              AND uf.user NOT IN (SELECT friend FROM user_friends WHERE user = ?)
        ");
        $stmt->execute([$current_user, $current_user]);
        $subscribers_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $subscribers_count = 0;
    }
    try {
        $stmt = $db->prepare("SELECT home_block_type, recs_enabled FROM users WHERE login = ? LIMIT 1");
        $stmt->execute([$current_user]);
        $settings_row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $hbt = (string)($settings_row['home_block_type'] ?? 'recent_added');
        if ($hbt === 'recent_viewed') {
            $home_block_type = 'recent_viewed';
        }
        if (isset($settings_row['recs_enabled']) && (string)$settings_row['recs_enabled'] === '0') {
            $show_recs_block = false;
        }
    } catch (Exception $e) {
        $home_block_type = 'recent_added';
        $show_recs_block = true;
    }
    try {
        $stmt = $db->prepare("SELECT email FROM users WHERE login = ? LIMIT 1");
        $stmt->execute([$current_user]);
        $account_email = (string)$stmt->fetchColumn();
    } catch (Exception $e) {
        $account_email = '';
    }
    try {
        $stmt = $db->prepare("SELECT COUNT(DISTINCT video_id) FROM video_views WHERE user = ?");
        $stmt->execute([$current_user]);
        $account_videos_watched = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $account_videos_watched = 0;
    }
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
        $stmt->execute([$current_user]);
        $account_videos_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $account_videos_count = 0;
    }
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_favourites WHERE user = ?");
        $stmt->execute([$current_user]);
        $account_favourites_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $account_favourites_count = 0;
    }
    try {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT uf.user)
            FROM user_favourites uf
            INNER JOIN videos v ON v.id = uf.video_id
            WHERE v.user = ?
              AND v.private = 0
              AND uf.user != ?
        ");
        $stmt->execute([$current_user, $current_user]);
        $account_fans_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $account_fans_count = 0;
    }
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM videos
            WHERE user IN (SELECT friend FROM user_friends WHERE user = ?)
              AND private = 0
        ");
        $stmt->execute([$current_user]);
        $account_friends_videos_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $account_friends_videos_count = 0;
    }
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM user_favourites
            WHERE user IN (SELECT friend FROM user_friends WHERE user = ?)
        ");
        $stmt->execute([$current_user]);
        $account_friends_favourites_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $account_friends_favourites_count = 0;
    }
    try {
        $account_unread_mail = count_unread_mail($db, $current_user);
    } catch (Exception $e) {
        $account_unread_mail = 0;
    }
    $account_mail_icon = $account_unread_mail > 0 ? 'img/mail_unread.gif' : 'img/mail.gif';

    $userStats = [
        'video_views' => $video_views,
        'channel_views' => $channel_views,
        'friends' => $friends_count
    ];
}

function time_ago($time) {
    $diff = time() - $time;
    if ($diff < 60) return $diff.' секунд назад';
    $mins = floor($diff/60);
    if ($mins < 60) {
        $n = $mins;
        $f = ($n%10==1 && $n%100!=11) ? 'минуту' : (($n%10>=2 && $n%10<=4 && ($n%100<10 || $n%100>=20)) ? 'минуты' : 'минут');
        return "$n $f назад";
    }
    $hours = floor($mins/60);
    if ($hours < 24) {
        $n = $hours;
        $f = ($n%10==1 && $n%100!=11) ? 'час' : (($n%10>=2 && $n%10<=4 && ($n%100<10 || $n%100>=20)) ? 'часа' : 'часов');
        return "$n $f назад";
    }
    $days = floor($hours/24);
    if ($days < 7) {
        $n = $days;
        $f = ($n%10==1 && $n%100!=11) ? 'день' : (($n%10>=2 && $n%10<=4 && ($n%100<10 || $n%100>=20)) ? 'дня' : 'дней');
        return "$n $f назад";
    }
    $weeks = floor($days/7);
    if ($weeks < 5) {
        $n = $weeks;
        $f = ($n%10==1 && $n%100!=11) ? 'неделю' : (($n%10>=2 && $n%10<=4 && ($n%100<10 || $n%100>=20)) ? 'недели' : 'недель');
        return "$n $f назад";
    }
    $months = floor($days/30);
    if ($months < 12) {
        $n = $months;
        $f = ($n%10==1 && $n%100!=11) ? 'месяц' : (($n%10>=2 && $n%10<=4 && ($n%100<10 || $n%100>=20)) ? 'месяца' : 'месяцев');
        return "$n $f назад";
    }
    $years = floor($days/365);
    $n = $years;
    $f = ($n%10==1 && $n%100!=11) ? 'год' : (($n%10>=2 && $n%10<=4 && ($n%100<10 || $n%100>=20)) ? 'года' : 'лет');
    return "$n $f назад";
}

function get_home_rating_stats($db, $video_id) {
    $stmt = $db->query("SELECT COUNT(*) as cnt, AVG(rating) as avg_rating FROM ratings WHERE video_id = ".intval($video_id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = intval($row['cnt'] ?? 0);
    $avg = $row['avg_rating'] !== null ? round((float)$row['avg_rating'], 1) : 0.0;
    return [$count, $avg];
}

function render_avg_stars_html($avg, $count) {
    $remaining = floatval($avg);
    $parts = [];
    for ($i=0; $i<5; $i++) {
        if ($remaining >= 0.75) $parts[] = 'full';
        elseif ($remaining >= 0.25) $parts[] = 'half';
        else $parts[] = 'empty';
        $remaining = max(0.0, $remaining - 1.0);
    }
    ob_start();
    ?>
    <div style="margin:2px 0 2px 0;">
      <nobr>
        <img src="img/star_smn<?=($parts[0]==='full'?'':($parts[0]==='half'?'_half':'_bg'))?>.gif" style="border:0; padding:0px; margin:0px; vertical-align:middle;">
        <img src="img/star_smn<?=($parts[1]==='full'?'':($parts[1]==='half'?'_half':'_bg'))?>.gif" style="border:0; padding:0px; margin:0px; vertical-align:middle;">
        <img src="img/star_smn<?=($parts[2]==='full'?'':($parts[2]==='half'?'_half':'_bg'))?>.gif" style="border:0; padding:0px; margin:0px; vertical-align:middle;">
        <img src="img/star_smn<?=($parts[3]==='full'?'':($parts[3]==='half'?'_half':'_bg'))?>.gif" style="border:0; padding:0px; margin:0px; vertical-align:middle;">
        <img src="img/star_smn<?=($parts[4]==='full'?'':($parts[4]==='half'?'_half':'_bg'))?>.gif" style="border:0; padding:0px; margin:0px; vertical-align:middle;">
      </nobr>
      <span style="color:#666666; font-size:smaller;">(<?=intval($count)?> оценок)</span>
    </div>
    <?php
    return ob_get_clean();
}

function calculate_trending_score($video, $comments, $rating_avg, $rating_count) {
  $age_hours = max(1, (time() - strtotime($video['time'])) / 3600);
  $views_rate = $video['views'] / pow($age_hours, 1.2);
  $comments_score = $comments * 2;
  $rating_score = $rating_avg * log(1 + $rating_count) * 10;
  $fresh_bonus = ($age_hours < 24) ? 50 : 0;
  return $views_rate + $comments_score + $rating_score + $fresh_bonus;
}  

$recent_videos = [];
$recent_block_mode = 'recent_added';
if (isset($_SESSION['user']) && isset($home_block_type) && $home_block_type === 'recent_viewed') {
    try {
        $scan_limit = 300;
        $stmt = $db->prepare("SELECT video_id, viewed_at FROM video_views ORDER BY viewed_at DESC LIMIT ?");
        $stmt->bindValue(1, (int)$scan_limit, PDO::PARAM_INT);
        $stmt->execute();
        $view_rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $picked = [];
        $ordered_ids = [];
        foreach ($view_rows as $vr) {
            $vid = (int)($vr['video_id'] ?? 0);
            $vts = (int)($vr['viewed_at'] ?? 0);
            if ($vid <= 0 || $vts <= 0) continue;
            if (isset($picked[$vid])) continue;
            $picked[$vid] = $vts;
            $ordered_ids[] = $vid;
            if (count($ordered_ids) >= 30) break;
        }
        if (!empty($ordered_ids)) {
            $in = implode(',', array_fill(0, count($ordered_ids), '?'));
            $stmtV = $db->prepare("SELECT * FROM videos WHERE id IN ($in) AND (private = 0 OR private IS NULL)");
            $stmtV->execute($ordered_ids);
            $videos_rows = $stmtV->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $by_id = [];
            foreach ($videos_rows as $vr) {
                $by_id[(int)$vr['id']] = $vr;
            }
            $recent_videos = [];
            foreach ($ordered_ids as $vid) {
                if (!isset($by_id[$vid])) continue;
                $row = $by_id[$vid];
                $row['last_viewed_at'] = (int)$picked[$vid];
                $recent_videos[] = $row;
                if (count($recent_videos) >= 10) break;
            }
        }
        if (!empty($recent_videos)) {
            $recent_block_mode = 'recent_viewed';
        }
    } catch (Exception $e) {
        $recent_videos = [];
    }
}
if (empty($recent_videos)) {
    $stmt = $db->query("SELECT * FROM videos ORDER BY id DESC LIMIT 10");
    $recent_videos = array_filter($stmt->fetchAll(), function($v) {
        if (!empty($v['private'])) return false;
        return !is_user_shadow_banned($v['user'] ?? '');
    });
}
$recent_videos = array_values(array_filter($recent_videos, function($v) {
    return !is_user_shadow_banned($v['user'] ?? '');
}));

$stmt = $db->query("SELECT * FROM videos WHERE private = 0 ORDER BY id DESC LIMIT 600");
$newest_pool = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $db->query("SELECT * FROM videos WHERE private = 0 ORDER BY views DESC LIMIT 150");
$views_pool = $stmt->fetchAll(PDO::FETCH_ASSOC);
$merged = [];
foreach ($newest_pool as $v) {
    $merged[(int)$v['id']] = $v;
}
foreach ($views_pool as $v) {
    $merged[(int)$v['id']] = $v;
}
$all_videos = array_filter($merged, function($v) {
    return !is_user_shadow_banned($v['user'] ?? '');
});

$promoted_map = [];
try {
    $rowsP = $db->query("SELECT video_id FROM video_promotions")->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
    foreach ($rowsP as $pvid) {
        $pid = (int)$pvid;
        if ($pid > 0) $promoted_map[$pid] = true;
    }
} catch (Exception $e) {
    $promoted_map = [];
}

if (!empty($promoted_map)) {
    try {
        $ids = array_keys($promoted_map);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stProm = $db->prepare("SELECT * FROM videos WHERE id IN ($ph) AND private = 0");
        $stProm->execute($ids);
        $promoted_rows = $stProm->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($promoted_rows as $pv) {
            if (is_user_shadow_banned($pv['user'] ?? '')) continue;
            $all_videos[(int)$pv['id']] = $pv;
        }
    } catch (Exception $e) {
    }
}

$featured_videos = [];
$featured_seen = [];

foreach ($all_videos as $video) {
    $vid = (int)($video['id'] ?? 0);
    if ($vid <= 0 || isset($featured_seen[$vid])) {
        continue;
    }
    $featured_seen[$vid] = true;

    $video_time = strtotime($video['time']);
    if ($video_time === false) {
        continue;
    }
    $age_hours = max(1, (time() - $video_time) / 3600);

    if ($age_hours > 168) continue;

    $comments_count = 0;
    try {
        $stmtCc = $db->prepare("SELECT COUNT(*) FROM comments WHERE video_id = ?");
        $stmtCc->execute([$video['id']]);
        $comments_count = (int)$stmtCc->fetchColumn();
    } catch (Exception $e) {
        $comments_count = 0;
    }

    list($rc, $ra) = get_home_rating_stats($db, $video['id']);

    $score = calculate_trending_score($video, $comments_count, $ra, $rc);
    if (!empty($promoted_map[(int)$video['id']])) {
        $score += 1000000;
    }
    if ($score < 0) {
        continue;
    }

    $featured_videos[] = [
        'video' => $video,
        'score' => $score
    ];
}

usort($featured_videos, function($a, $b) {
    return $b['score'] <=> $a['score'];
});

$featured_videos = array_slice($featured_videos, 0, 10);

showHeader("Главная");
?>

<div class="guide-layout-container enable-fancy-subscribe-button">
    <div class="guide-container">
        <?php if (!isset($_SESSION['user'])): ?>
        <div id="guide-builder-promo">
            <h2>Sign in to add channels to your homepage</h2>
            <div id="guide-builder-promo-buttons" class="signed-out">
                <button type="button" class="yt-uix-button yt-uix-button-dark" onclick="window.location.href='login.php';" role="button"><span class="yt-uix-button-content">Sign In</span></button>
                <button type="button" class="yt-uix-button yt-uix-button-primary" onclick="window.location.href='register.php';" role="button"><span class="yt-uix-button-content">Create Account</span></button>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="guide" data-last-clicked-item="feed-system-youtube">
            <div class="guide-section yt-uix-expander first">
                <h3 class="guide-item-container selected-child">
                    <a class="guide-item selected" href="#">
                        <span class="thumb">
                            <img src="img/pixel.gif" alt="" class="system-icon category">
                        </span>
                        <span class="display-name">From YouTube</span>
                    </a>
                </h3>
                <ul>
                    <li class="guide-item-container"><a class="guide-item" href="results.php?search_query=trending"><span class="thumb"><img class="system-icon system trending" src="img/pixel.gif" alt=""></span><span class="display-name">Trending</span></a></li>
                    <li class="guide-item-container"><a class="guide-item" href="results.php?search_query=popular"><span class="thumb"><img class="system-icon system popular" src="img/pixel.gif"alt=""></span><span class="display-name">Popular</span></a></li>
                    <li class="guide-item-container"><a class="guide-item" href="results.php?search_query=music"><span class="thumb"><img class="system-icon system music" src="img/pixel.gif" alt=""></span><span class="display-name">Music</span></a></li>
                    <li class="guide-item-container"><a class="guide-item" href="results.php?search_query=entertainment"><span class="thumb"><img class="system-icon chart entertainment" src="img/pixel.gif" alt=""></span><span class="display-name">Entertainment</span></a></li>
                    <li class="guide-item-container"><a class="guide-item" href="results.php?search_query=sports"><span class="thumb"><img class="system-icon chart sports" src="img/pixel.gif" alt=""></span><span class="display-name">Sports</span></a></li>
                    <li class="guide-item-container"><a class="guide-item" href="results.php?search_query=comedy"><span class="thumb"><img class="system-icon chart comedy" src="img/pixel.gif" alt=""></span><span class="display-name">Comedy</span></a></li>
                    <li class="guide-item-container"><a class="guide-item" href="results.php?search_query=film"><span class="thumb"><img class="system-icon chart film" src="img/pixel.gif" alt=""></span><span class="display-name">Film &amp; Animation</span></a></li>
                    <li class="guide-item-container"><a class="guide-item" href="results.php?search_query=gaming"><span class="thumb"><img class="system-icon chart gadgets" src="img/pixel.gif" alt=""></span><span class="display-name">Gaming</span></a></li>
                </ul>
                <div class="guide-item-container">
                    <span class="guide-item guide-item-action guide-item-fake">
                        <a href="channel.php">see all<img src="img/pixel.gif" class="see-more-arrow" alt=""></a>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="guide-background"></div>

    <div id="video-sidebar">
        <h3 class="sidebar-module-header">Spotlight</h3>
        <?php if (!empty($featured_videos)): ?>
        <h2><?= htmlspecialchars($featured_videos[0]['video']['title'] ?? 'Featured Video') ?></h2>
        <p class="sidebar-module-description">
            <?= htmlspecialchars(mb_substr($featured_videos[0]['video']['description'] ?? '', 0, 150)) ?>...
        </p>
        <ul>
            <?php for ($i = 0; $i < min(4, count($featured_videos)); $i++): $video = $featured_videos[$i]['video']; ?>
            <li class="video-list-item">
                <a href="watch?v=<?= htmlspecialchars($video['public_id'] ?? $video['id']) ?>" class="video-list-item-link">
                    <span class="ux-thumb-wrap contains-addto">
                        <span class="video-thumb ux-thumb yt-thumb-default-120">
                            <span class="yt-thumb-clip">
                                <span class="yt-thumb-clip-inner">
                                    <img src="<?= htmlspecialchars($video['preview'] ?? 'img/default.jpg') ?>" alt="<?= htmlspecialchars($video['title'] ?? '') ?>" width="120">
                                    <span class="vertical-align"></span>
                                </span>
                            </span>
                        </span>
                        <span class="video-time"><?= get_video_duration_fast($video['file'], $video['id'], $video['public_id'] ?? '') ?></span>
                    </span>
                    <span dir="ltr" class="title" title="<?= htmlspecialchars($video['title'] ?? '') ?>"><?= htmlspecialchars(mb_substr($video['title'] ?? '', 0, 50)) ?></span>
                    <span class="stat">by <span class="yt-user-name" dir="ltr"><?= htmlspecialchars($video['user'] ?? '') ?></span></span>
                    <span class="stat view-count"><span class="viewcount"><?= number_format($video['views'] ?? 0) ?> views</span></span>
                </a>
            </li>
            <?php endfor; ?>
        </ul>
        <?php endif; ?>

        <h3>Featured</h3>
        <ul>
            <?php 
            $featured_sidebar = array_slice($featured_videos, 4, 3);
            foreach ($featured_sidebar as $item): $video = $item['video']; ?>
            <li class="video-list-item">
                <a href="watch?v=<?= htmlspecialchars($video['public_id'] ?? $video['id']) ?>" class="video-list-item-link yt-uix-sessionlink">
                    <span class="ux-thumb-wrap contains-addto">
                        <span class="video-thumb ux-thumb yt-thumb-default-120">
                            <span class="yt-thumb-clip">
                                <span class="yt-thumb-clip-inner">
                                    <img src="<?= htmlspecialchars($video['preview'] ?? 'img/default.jpg') ?>" alt="<?= htmlspecialchars($video['title'] ?? '') ?>" width="120">
                                    <span class="vertical-align"></span>
                                </span>
                            </span>
                        </span>
                        <span class="video-time"><?= get_video_duration_fast($video['file'], $video['id'], $video['public_id'] ?? '') ?></span>
                    </span>
                    <span dir="ltr" class="title" title="<?= htmlspecialchars($video['title'] ?? '') ?>"><?= htmlspecialchars(mb_substr($video['title'] ?? '', 0, 50)) ?></span>
                    <span class="stat">by <span class="yt-user-name" dir="ltr"><?= htmlspecialchars($video['user'] ?? '') ?></span></span>
                    <span class="stat view-count"><span class="viewcount"><?= number_format($video['views'] ?? 0) ?> views</span></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div id="feed">
        <div id="feed-system-youtube" class="individual-feed" data-loaded="true" data-feed-name="youtube" data-feed-type="system" style="opacity: 1;">
            <div class="feed-header no-metadata">
                <div class="feed-header-thumb">
                    <img class="feed-header-icon youtube" src="img/pixel.gif" alt="">
                </div>
                <div class="feed-header-details">
                    <h2>From YouTube</h2>
                </div>
            </div>

            <div class="feed-container" data-filter-type="" data-view-type="">
                <div class="feed-page">
                    <ul>
                        <?php foreach ($featured_videos as $item): $video = $item['video']; 
                        $comments_count = 0;
                        try {
                            $stmtCc = $db->prepare("SELECT COUNT(*) FROM comments WHERE video_id = ?");
                            $stmtCc->execute([$video['id']]);
                            $comments_count = (int)$stmtCc->fetchColumn();
                        } catch (Exception $e) {}
                        list($rc, $ra) = get_home_rating_stats($db, $video['id']);
                        $video_time = strtotime($video['time']);
                        $time_ago_str = $video_time ? time_ago($video_time) : 'недавно';
                        ?>
                        <li>
                            <div class="feed-item-container yt-uix-expander yt-uix-expander-collapsed">
                                <div class="feed-item-outer">
                                    <div class="feed-item-main">
                                        <div class="feed-item-thumb">
                                            <a class="ux-thumb-wrap contains-addto yt-uix-sessionlink" href="watch?v=<?= htmlspecialchars($video['public_id'] ?? $video['id']) ?>">
                                                <span class="video-thumb ux-thumb yt-thumb-default-106">
                                                    <span class="yt-thumb-clip">
                                                        <span class="yt-thumb-clip-inner">
                                                            <img src="<?= htmlspecialchars($video['preview'] ?? 'img/default.jpg') ?>" alt="Thumbnail" width="106">
                                                            <span class="vertical-align"></span>
                                                        </span>
                                                    </span>
                                                </span>
                                                <span class="video-time"><?= get_video_duration_fast($video['file'], $video['id'], $video['public_id'] ?? '') ?></span>
                                            </a>
                                        </div>
                                        <div class="feed-item-time"><?= $time_ago_str ?></div>
                                        <div class="feed-item-content">
                                            <h4>
                                                <a class="title yt-uix-sessionlink" href="watch?v=<?= htmlspecialchars($video['public_id'] ?? $video['id']) ?>" dir="ltr">
                                                    <?= htmlspecialchars($video['title'] ?? '') ?>
                                                </a>
                                            </h4>
                                            <div class="description">
                                                <p><?= htmlspecialchars(mb_substr($video['description'] ?? '', 0, 150)) ?>...</p>
                                            </div>
                                            <div class="metadata">
                                                <p>
                                                    <a href="/user/<?= urlencode($video['user'] ?? '') ?>" class="yt-user-name" dir="ltr"><?= htmlspecialchars($video['user'] ?? '') ?></a>
                                                    <span class="bull">•</span>
                                                    <span class="view-count"><?= number_format($video['views'] ?? 0) ?> views</span>
                                                </p>
                                            </div>
                                            <?= render_avg_stars_html($ra, $rc) ?>
                                            <div class="feed-item-actions-line">
                                                <span class="feed-item-author">
                                                    <a href="/user/<?= urlencode($video['user'] ?? '') ?>" class="yt-user-photo">
                                                        <span class="video-thumb ux-thumb yt-thumb-square-24">
                                                            <span class="yt-thumb-clip">
                                                                <span class="yt-thumb-clip-inner">
                                                                    <img src="img/default_avatar.jpg" alt="<?= htmlspecialchars($video['user'] ?? '') ?>" width="24">
                                                                    <span class="vertical-align"></span>
                                                                </span>
                                                            </span>
                                                        </span>
                                                    </a>
                                                </span>
                                                <span class="feed-item-owner">
                                                    <a href="/user/<?= urlencode($video['user'] ?? '') ?>" class="yt-user-name" dir="ltr"><?= htmlspecialchars($video['user'] ?? '') ?></a>
                                                </span> uploaded
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div id="footer-ads"></div>
</div>

<?php showFooter(); ?>