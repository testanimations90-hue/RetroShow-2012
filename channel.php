<?php
include("init.php");
include('template.php');
require_once __DIR__ . '/recs.php';

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'recent';

function time_ago($time) {
    $diff = time() - $time;
    if ($diff < 60) return $diff.' секунд назад';
    $mins = floor($diff/60);
    if ($mins < 60) return $mins.' минут назад';
    $hours = floor($mins/60);
    if ($hours < 24) return $hours.' часов назад';
    $days = floor($hours/24);
    if ($days < 7) return $days.' дней назад';
    $weeks = floor($days/7);
    if ($weeks < 5) return $weeks.' недель назад';
    $months = floor($days/30);
    if ($months < 12) return $months.' месяцев назад';
    $years = floor($days/365);
    return $years.' лет назад';
}

function rus_date($time) {
    $months = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня',
        7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
    ];
    $d = date('j', $time);
    $m = $months[intval(date('n', $time))];
    $y = date('Y', $time);
    return "$d $m $y";
}

function channel_video_rus_date_from_db($timeVal): string {
    if ($timeVal === null || $timeVal === '') {
        return rus_date(time());
    }
    if (is_numeric($timeVal)) {
        $ts = (int)$timeVal;
        if ($ts > 100000) {
            return rus_date($ts);
        }
    }
    $s = trim((string)$timeVal);
    foreach (['d.m.Y, H:i', 'd.m.Y H:i'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $s);
        if ($dt instanceof DateTime) {
            return rus_date((int)$dt->format('U'));
        }
    }
    $ts = strtotime(str_replace(',', '', $s));
    if ($ts !== false && $ts > 86400) {
        return rus_date($ts);
    }
    return rus_date(time());
}

function channel_comment_body_html($text) {
    return nl2br(htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'), false);
}

require_once 'duration_helper.php';

function get_video_duration($file, $id, $public_id = '') {
    return get_video_duration_fast($file, $id, $public_id);
}

function channel_get_rating_stats($db, $video_id) {
    $stmt = $db->query("SELECT COUNT(*) as cnt, AVG(rating) as avg_rating FROM ratings WHERE video_id = ".intval($video_id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = intval($row['cnt'] ?? 0);
    $avg = $row['avg_rating'] !== null ? round((float)$row['avg_rating'], 1) : 0.0;
    return [$count, $avg];
}

function channel_render_avg_stars_html($avg, $count, $show_count_rating = true) {
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
      <?php if ($show_count_rating): ?>
        <span style="color:#666666; font-size:smaller;">(<?=intval($count)?> оценок)</span>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function get_user_profile_icon_setting($username) {
    global $db;
    try {
        $stmt = $db->prepare('SELECT profile_icon FROM users WHERE login = ?');
        $stmt->execute([$username]);
        $val = $stmt->fetchColumn();
        return ($val === '1') ? '1' : '0';
    } catch (Exception $e) {
        return '0';
    }
}

$user = isset($_GET['user']) ? $_GET['user'] : null;

function get_profile_icon($username, $profile_icon_setting = '0') {
    static $icon_cache = [];
    
    $cache_key = $username . '_' . $profile_icon_setting;
    if (isset($icon_cache[$cache_key])) {
        return $icon_cache[$cache_key];
    }
    
    if ($profile_icon_setting === '1') {
        global $db;
        try {
            $stmt = $db->prepare('SELECT profile_icon_custom FROM users WHERE login = ?');
            $stmt->execute([$username]);
            $custom = $stmt->fetchColumn();
            if ($custom && is_string($custom) && $custom !== '') {
                $icon_cache[$cache_key] = $custom;
                return $custom;
            }
        } catch (Exception $e) {
        }
        $icon_cache[$cache_key] = 'img/no_videos_140.jpg';
        return 'img/no_videos_140.jpg';
    }
    
    global $db;
    $stmt = $db->prepare("SELECT preview FROM videos WHERE user = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$username]);
    $last_video = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last_video && $last_video['preview']) {
        $icon_cache[$cache_key] = $last_video['preview'];
        return $last_video['preview'];
    }
    
    $icon_cache[$cache_key] = 'img/no_videos_140.jpg';
    return 'img/no_videos_140.jpg';
}

$user_data = null;
if ($user) {
    try {
        $stmt_user = $db->prepare('SELECT about_me, gender, birthday_yr, birthday_mon, birthday_day, country, name, last_n, website, city, hometown, profile_comm, profile_icon, profile_icon_custom FROM users WHERE login = ?');
        $stmt_user->execute([$user]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $user_data = null;
    }
}

if ($user && !$user_data) {
    showHeader('Канал не найден');
    echo '<div class="errorBox">Канал не найден!</div>';
    showFooter();
    exit;
}

$about_me = '';
if ($user_data && isset($user_data['about_me'])) {
  $about_me = trim($user_data['about_me']);
}

$fav_count = 0;
$comments_count = 0;
try {
    $stmtFav = $db->prepare("SELECT COUNT(*) FROM user_favourites WHERE user = ?");
    $stmtFav->execute([$user]);
    $fav_count = (int)$stmtFav->fetchColumn();
} catch (Exception $e) {}
try {
    $stmtPc = $db->prepare("SELECT COUNT(*) FROM profile_comments WHERE profile_user = ?");
    $stmtPc->execute([$user]);
    $comments_count = (int)$stmtPc->fetchColumn();
} catch (Exception $e) {}

$profile_comments_preview = [];
try {
    $stmtPcPrev = $db->prepare('SELECT time, user, text FROM profile_comments WHERE profile_user = ? ORDER BY time DESC, id DESC LIMIT 3');
    $stmtPcPrev->execute([$user]);
    $profile_comments_preview = $stmtPcPrev->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
}

$subscribers_count = 0;
$my_friends = [];
try {
    $stmtMy = $db->prepare("SELECT friend FROM user_friends WHERE user = ?");
    $stmtMy->execute([$user]);
    $my_friends = $stmtMy->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
} catch (Exception $e) {}
try {
    $stmtSub = $db->prepare("
        SELECT COUNT(*) 
        FROM user_friends uf
        WHERE uf.friend = ?
          AND uf.user NOT IN (SELECT friend FROM user_friends WHERE user = ?)
    ");
    $stmtSub->execute([$user, $user]);
    $subscribers_count = (int)$stmtSub->fetchColumn();
} catch (Exception $e) {}

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 5;
$offset = ($page - 1) * $per_page;

if (!$user && (!isset($_GET['tab']) || $_GET['tab'] === '')) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'recent';
    $filter_name = 'Последние';
    
    switch ($filter) {
        case 'recent': $filter_name = 'Последние'; $order_by = 'id DESC'; break;
        case 'viewed': $filter_name = 'Популярные'; $order_by = 'views DESC, id DESC'; break;
        case 'rated': $filter_name = 'Высоко оцененные'; break;
        case 'discussed': $filter_name = 'Обсуждаемые'; break;
        case 'favorites': $filter_name = 'Избранные'; break;
        case 'random': $filter_name = 'Случайные'; break;
    }
    
    if ($filter == 'rated') {
        $stmt = $db->prepare("SELECT v.id, v.public_id, v.title, v.preview, v.description, v.time, v.views, v.user, v.file, 
            COALESCE(AVG(r.rating),0) AS avg_rating, COUNT(r.id) AS votes_count
            FROM videos v
            LEFT JOIN ratings r ON r.video_id = v.id
            WHERE v.private = 0
            GROUP BY v.id
            ORDER BY avg_rating DESC, v.views DESC LIMIT $offset, $per_page");
        $stmt->execute();
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $db->query("SELECT COUNT(*) FROM videos WHERE private = 0");
        $total = $stmt->fetchColumn();
        $total_pages = ceil($total / $per_page);
    } elseif ($filter == 'random') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM videos WHERE private = 0");
        $stmt->execute();
        $total = $stmt->fetchColumn();
        $total_pages = ceil($total / $per_page);
        $stmt = $db->prepare("SELECT id, public_id, title, preview, description, time, views, user, file FROM videos WHERE private = 0 ORDER BY RANDOM() LIMIT $offset, $per_page");
        $stmt->execute();
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM videos WHERE private = 0");
        $stmt->execute();
        $total = $stmt->fetchColumn();
        $total_pages = ceil($total / $per_page);
        $stmt = $db->prepare("SELECT id, public_id, title, preview, description, time, views, user, file FROM videos WHERE private = 0 ORDER BY $order_by LIMIT $offset, $per_page");
        $stmt->execute();
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    showHeader($filter_name . ' видео');
    ?>
    <div class="moduleTitleBar">
        <div class="moduleTitle"><?= $filter_name ?> видео</div>
    </div>
    
    <div style="padding: 10px; margin-bottom: 15px; background: #f5f5f5; border: 1px solid #ddd;">
        <a href="channel.php?filter=recent" <?= $filter == 'recent' ? 'style="font-weight:bold"' : '' ?>>Последние</a> |
        <a href="channel.php?filter=viewed" <?= $filter == 'viewed' ? 'style="font-weight:bold"' : '' ?>>Популярные</a> |
        <a href="channel.php?filter=rated" <?= $filter == 'rated' ? 'style="font-weight:bold"' : '' ?>>Высоко оцененные</a> |
        <a href="channel.php?filter=discussed" <?= $filter == 'discussed' ? 'style="font-weight:bold"' : '' ?>>Обсуждаемые</a> |
        <a href="channel.php?filter=favorites" <?= $filter == 'favorites' ? 'style="font-weight:bold"' : '' ?>>Избранные</a> |
        <a href="channel.php?filter=random" <?= $filter == 'random' ? 'style="font-weight:bold"' : '' ?>>Случайные</a>
    </div>
    
    <?php foreach ($videos as $video): ?>
    <div class="moduleEntry">
        <table width="100%">
            <tr valign="top">
                <td width="120"><a href="watch?v=<?= htmlspecialchars($video['public_id'] ?? $video['id']) ?>"><img src="<?= htmlspecialchars($video['preview']) ?>" width="120" height="90"></a></td>
                <td>
                    <div class="moduleEntryTitle"><a href="watch?v=<?= htmlspecialchars($video['public_id'] ?? $video['id']) ?>"><?= htmlspecialchars($video['title']) ?></a></div>
                    <div class="moduleEntryDetails">Добавлено: <?= time_ago(strtotime($video['time'])) ?> от <a href="/user/<?= urlencode($video['user']) ?>"><?= htmlspecialchars($video['user']) ?></a></div>
                    <div class="moduleEntryDetails">Просмотров: <?= number_format($video['views']) ?></div>
                </td>
            </tr>
        </table>
    </div>
    <div style="border-bottom:1px solid #ccc; margin:5px 0"></div>
    <?php endforeach; ?>
    
    <?php if ($total_pages > 1): ?>
    <div class="pagingDiv" style="margin: 10px 0;">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="pagerCurrent"><?= $i ?></span>
            <?php else: ?>
                <span class="pagerNotCurrent"><a href="?filter=<?= $filter ?>&page=<?= $i ?>"><?= $i ?></a></span>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php
    showFooter();
    exit;
}

if ($user && isset($_GET['tab']) && $_GET['tab'] === 'videos') {
    $view = isset($_GET['view']) ? $_GET['view'] : 'public';
    $is_owner = isset($_SESSION['user']) && $_SESSION['user'] === $user;
    
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    if ($view === 'public' || !$is_owner) {
        $stmt_total = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
        $stmt_total->execute([$user]);
        $total = $stmt_total->fetchColumn();
        
        $stmt = $db->prepare("SELECT id, public_id, title, preview, description, time, views, user, file FROM videos WHERE user = ? AND private = 0 ORDER BY id DESC LIMIT $offset, $per_page");
        $stmt->execute([$user]);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt_total = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 1");
        $stmt_total->execute([$user]);
        $total = $stmt_total->fetchColumn();
        
        $stmt = $db->prepare("SELECT id, public_id, title, preview, description, time, views, user, file FROM videos WHERE user = ? AND private = 1 ORDER BY id DESC LIMIT $offset, $per_page");
        $stmt->execute([$user]);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $total_pages = ceil($total / $per_page);
    
    showHeader('Видео ' . htmlspecialchars($user));
    ?>
<div id="branded-page-header-container" class="ytg-wide banner-displayed-mode">
    <div id="branded-page-header" class="ytg-wide">
        <div id="channel-header-main">
            <div class="upper-section clearfix">
                <a href="/user/<?= urlencode($user) ?>">
                    <span class="profile-thumb">
                        <span class="centering-wrap">
                            <?php
                            $profile_icon = 'img/no_videos_140.jpg';
                            if (!empty($user_data['profile_icon_custom'])) {
                                $profile_icon = $user_data['profile_icon_custom'];
                            } elseif (!empty($user_data['profile_icon']) && $user_data['profile_icon'] === '1') {
                                $stmtIcon = $db->prepare("SELECT profile_icon_custom FROM users WHERE login = ?");
                                $stmtIcon->execute([$user]);
                                $customIcon = $stmtIcon->fetchColumn();
                                if ($customIcon && is_string($customIcon) && $customIcon !== '') {
                                    $profile_icon = $customIcon;
                                }
                            } else {
                                $stmtLastVideo = $db->prepare("SELECT preview FROM videos WHERE user = ? AND private = 0 ORDER BY id DESC LIMIT 1");
                                $stmtLastVideo->execute([$user]);
                                $lastVideo = $stmtLastVideo->fetch(PDO::FETCH_ASSOC);
                                if ($lastVideo && !empty($lastVideo['preview'])) {
                                    $profile_icon = $lastVideo['preview'];
                                }
                            }
                            ?>
                            <img src="<?= htmlspecialchars($profile_icon, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8') ?>">
                        </span>
                    </span>
                </a>
                <div class="upper-left-section">
                    <h1><?= htmlspecialchars($user_data['username'] ?? $user, ENT_QUOTES, 'UTF-8') ?></h1>
                </div>

                <div class="upper-left-section enable-fancy-subscribe-button">
                    <?php
                    $is_subscribed = false;
                    if (isset($_SESSION['user']) && $_SESSION['user'] !== $user) {
                        try {
                            $stmtSub = $db->prepare("SELECT 1 FROM user_friends WHERE user = ? AND friend = ? LIMIT 1");
                            $stmtSub->execute([$_SESSION['user'], $user]);
                            $is_subscribed = (bool)$stmtSub->fetchColumn();
                        } catch (Exception $e) {
                            $is_subscribed = false;
                        }
                    }
                    ?>
                    <div class="yt-subscription-button-hovercard yt-uix-hovercard">
                        <?php if (!isset($_SESSION['user'])): ?>
                        <button onclick="window.location.href='login.php';" type="button" class="yt-subscription-button subscription-button-with-recommended-channels yt-uix-button yt-uix-button-subscription yt-uix-tooltip" role="button">
                            <span class="yt-uix-button-icon-wrapper"><img class="yt-uix-button-icon yt-uix-button-icon-subscribe" src="img/pixel.gif" alt=""></span>
                            <span class="yt-uix-button-content"><span class="subscribe-label">Subscribe</span></span>
                        </button>
                        <?php elseif ($is_subscribed): ?>
                        <button onclick="window.location.href='/user/<?= urlencode($user) ?>&unsubscribe=1';" type="button" class="yt-subscription-button subscription-button-with-recommended-channels yt-uix-button yt-uix-button-subscribed yt-uix-tooltip" role="button">
                            <span class="yt-uix-button-icon-wrapper"><img class="yt-uix-button-icon yt-uix-button-icon-subscribe" src="img/pixel.gif" alt=""></span>
                            <span class="yt-uix-button-content"><span class="subscribed-label">Subscribed</span></span>
                        </button>
                        <?php else: ?>
                        <form method="post" action="/user/<?= urlencode($user) ?>" style="display:inline; margin:0;">
                            <input type="hidden" name="add_friend" value="1">
                            <button type="submit" class="yt-subscription-button subscription-button-with-recommended-channels yt-uix-button yt-uix-button-subscription yt-uix-tooltip" role="button">
                                <span class="yt-uix-button-icon-wrapper"><img class="yt-uix-button-icon yt-uix-button-icon-subscribe" src="img/pixel.gif" alt=""></span>
                                <span class="yt-uix-button-content"><span class="subscribe-label">Subscribe</span></span>
                            </button>
                        </form>
                        <?php endif; ?>
                        <div class="yt-uix-hovercard-content hid">
                            <p class="loading-spinner"><img src="img/pixel.gif" alt="">Loading...</p>
                        </div>
                    </div>
                </div>
                <div class="upper-right-section">
                    <div class="header-stats">
                        <?php
                        $subscribers_count = 0;
                        try {
                            $stmtSubs = $db->prepare("SELECT COUNT(*) FROM user_friends WHERE friend = ?");
                            $stmtSubs->execute([$user]);
                            $subscribers_count = (int)$stmtSubs->fetchColumn();
                        } catch (Exception $e) {
                            $subscribers_count = 0;
                        }

                        $total_views = 0;
                        try {
                            $stmtViews = $db->prepare("SELECT SUM(views) AS total_views FROM videos WHERE user = ? AND private = 0");
                            $stmtViews->execute([$user]);
                            $total_views = (int)$stmtViews->fetchColumn();
                        } catch (Exception $e) {
                            $total_views = 0;
                        }

                        $video_count = 0;
                        try {
                            $stmtVidCnt = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
                            $stmtVidCnt->execute([$user]);
                            $video_count = (int)$stmtVidCnt->fetchColumn();
                        } catch (Exception $e) {
                            $video_count = 0;
                        }
                        ?>
                        <div class="stat-entry">
                            <span class="stat-value"><?= number_format($subscribers_count) ?></span>
                            <span class="stat-name">subscribers</span>
                        </div>

                        <div class="stat-entry">
                            <span class="stat-value"><?= number_format($total_views) ?></span>
                            <span class="stat-name">video views</span>
                        </div>
                    </div>
                    <span class="valign-shim"></span>
                </div>
            </div>
    <div id="branded-page-header-container" class="ytg-wide banner-displayed-mode">
        <div id="branded-page-header" class="ytg-wide">
            <div id="channel-header-main">
                <div class="channel-horizontal-menu clearfix">
                    <ul>
                        <li class="<?= (!isset($_GET['tab']) || $_GET['tab'] === '' || $_GET['tab'] === 'featured') ? 'selected' : '' ?>">
                            <a href="/user/<?= urlencode($user) ?>&tab=featured" class="gh-tab-100">Featured</a>
                        </li>
                        <li class="<?= (isset($_GET['tab']) && $_GET['tab'] === 'feed') ? 'selected' : '' ?>">
                            <a href="/user/<?= urlencode($user) ?>&tab=feed" class="gh-tab-102">Feed</a>
                        </li>
                        <li class="<?= (isset($_GET['tab']) && $_GET['tab'] === 'videos') ? 'selected' : '' ?>">
                            <a href="/user/<?= urlencode($user) ?>&tab=videos&view=public" class="gh-tab-101">Videos</a>
                        </li>
                        <li class="<?= (isset($_GET['tab']) && $_GET['tab'] === 'comments') ? 'selected' : '' ?>">
                            <a href="/user/<?= urlencode($user) ?>&tab=comments" class="gh-tab-103">Comments</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
   </div>
  </div>
 </div>
</div>
<div id="branded-page-body">
        <div class="channel-tab-content selected">
            <div class="tab-content-body">
                <div class="primary-pane">

                        <div class="moduleTitle"><?= $view === 'public' ? 'Публичные' : 'Приватные' ?> видео</div>

                    
                    <?php if (count($videos) == 0): ?>
                        <p>Нет видео</p>
                    <?php else: ?>
                        <?php foreach ($videos as $video): ?>
                        <div class="moduleEntry">
                            <table width="100%">
                                <tr valign="top">
                                    <td width="120">
                                        <a href="watch?v=<?= htmlspecialchars($video['public_id'] ?? $video['id']) ?>">
                                            <img src="<?= htmlspecialchars($video['preview']) ?>" width="120" height="90">
                                        </a>
                                    </td>
                                    <td>
                                        <div class="moduleEntryTitle">
                                            <a href="watch?v=<?= htmlspecialchars($video['public_id'] ?? $video['id']) ?>">
                                                <?= htmlspecialchars($video['title']) ?>
                                            </a>
                                        </div>
                                        <div class="moduleEntryDetails">
                                            Добавлено: <?= time_ago(strtotime($video['time'])) ?>
                                        </div>
                                        <div class="moduleEntryDetails">
                                            Просмотров: <?= number_format($video['views']) ?>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div style="border-bottom:1px solid #ccc; margin:5px 0"></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if ($total_pages > 1): ?>
                    <div class="pagingDiv" style="margin: 10px 0;">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="pagerCurrent"><?= $i ?></span>
                            <?php else: ?>
                                <span class="pagerNotCurrent">
                                    <a href="?user=<?= urlencode($user) ?>&tab=videos&view=<?= $view ?>&page=<?= $i ?>"><?= $i ?></a>
                                </span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    showFooter();
    exit;
}

if ($user && (!isset($_GET['tab']) || $_GET['tab'] === '')) {
    $is_owner_profile = isset($_SESSION['user']) && $_SESSION['user'] === $user;
    $are_friends = false;

    if (!$is_owner_profile && isset($_SESSION['user']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_friend'])) {
        try {
            $db->prepare("INSERT OR IGNORE INTO user_friends (user, friend, created_at) VALUES (?, ?, ?)")
                ->execute([$_SESSION['user'], $user, time()]);
        } catch (Exception $e) {
        }
        header('Location: /user/' . urlencode($user));
        exit;
    }
    
    if (!$is_owner_profile && isset($_SESSION['user'])) {
        try {
            $stAreFriends = $db->prepare("SELECT 1 FROM user_friends WHERE user = ? AND friend = ? LIMIT 1");
            $stAreFriends->execute([$_SESSION['user'], $user]);
            $are_friends = (bool)$stAreFriends->fetchColumn();
        } catch (Exception $e) {
            $are_friends = false;
        }
    }

    $stmt = $db->prepare('SELECT last_login, signup_time FROM users WHERE login = ?');
    $stmt->execute([$user]);
    $user_row = $stmt->fetch(PDO::FETCH_ASSOC);
    $last_login_time = isset($user_row['last_login']) ? intval($user_row['last_login']) : time();
    $signup_time = isset($user_row['signup_time']) ? intval($user_row['signup_time']) : time();

    $db->exec("CREATE TABLE IF NOT EXISTS user_stats (user TEXT PRIMARY KEY, profile_viewed INTEGER DEFAULT 0, videos_watched INTEGER DEFAULT 0)");
    if (isset($_SESSION['user']) && $_SESSION['user'] !== $user) {
        $db->exec("INSERT OR IGNORE INTO user_stats (user) VALUES (".$db->quote($user).")");
        $db->exec("UPDATE user_stats SET profile_viewed = profile_viewed + 1 WHERE user = " . $db->quote($user));
    }

    $stat = $db->query("SELECT profile_viewed FROM user_stats WHERE user = " . $db->quote($user));
    $profile_viewed = ($stat && ($row = $stat->fetch())) ? intval($row['profile_viewed']) : 0;

    $stmt_vw = $db->prepare("SELECT COUNT(DISTINCT video_id) FROM video_views WHERE user = ?");
    $stmt_vw->execute([$user]);
    $videos_watched = (int)$stmt_vw->fetchColumn();

    if (!function_exists('ago_ru')) {
        function ago_ru($ts) {
            $diff = time() - (int)$ts;
            if ($diff < 60) return $diff.' секунд назад';
            if ($diff < 3600) return floor($diff / 60).' минут назад';
            if ($diff < 86400) return floor($diff / 3600).' часов назад';
            if ($diff < 2592000) return floor($diff / 86400).' дней назад';
            if ($diff < 31536000) return floor($diff / 2592000).' месяцев назад';
            return floor($diff / 31536000).' лет назад';
        }
    }

    $public_count = 0;
    $private_count = 0;
    $friends_count = 0;
    try {
        $stPub = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
        $stPub->execute([$user]);
        $public_count = (int)$stPub->fetchColumn();
        if ($is_owner_profile) {
            $stPriv = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 1");
            $stPriv->execute([$user]);
            $private_count = (int)$stPriv->fetchColumn();
        }
        $stFr = $db->prepare("SELECT COUNT(*) FROM user_friends WHERE user = ?");
        $stFr->execute([$user]);
        $friends_count = (int)$stFr->fetchColumn();
    } catch (Exception $e) {
    }

    $latest_public = null;
    try {
        $stLatest = $db->prepare("SELECT id, public_id, title, preview, time, views FROM videos WHERE user = ? AND private = 0 ORDER BY id DESC LIMIT 1");
        $stLatest->execute([$user]);
        $latest_public = $stLatest->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        $latest_public = null;
    }

    $age_text = '';
    $birth_year = isset($user_data['birthday_yr']) ? trim((string)$user_data['birthday_yr']) : '';
    if ($birth_year !== '' && $birth_year !== '---') {
        $age_val = (int)date('Y') - (int)$birth_year;
        if ($age_val > 0 && $age_val < 120) {
            $n10 = $age_val % 10;
            $n100 = $age_val % 100;
            if ($n10 === 1 && $n100 !== 11) {
                $age_word = 'год';
            } elseif ($n10 >= 2 && $n10 <= 4 && ($n100 < 10 || $n100 >= 20)) {
                $age_word = 'года';
            } else {
                $age_word = 'лет';
            }
            $age_text = $age_val . ' ' . $age_word;
        }
    }

    $name_text = trim((string)($user_data['name'] ?? '') . ' ' . (string)($user_data['last_n'] ?? ''));
    $about_text = trim((string)($user_data['about_me'] ?? ''));

    $website_raw = trim((string)($user_data['website'] ?? ''));
    $website_href = $website_raw;
    if ($website_href !== '' && !preg_match('/^https?:\/\//i', $website_href)) {
        $website_href = 'http://' . $website_href;
    }

    showHeader('Профиль ' . htmlspecialchars($user));
    
    ?>
   <div id="branded-page-header-container" class="ytg-wide banner-displayed-mode">
    <div id="branded-page-header" class="ytg-wide">
        <div id="channel-header-main">
            <div class="upper-section clearfix">
                <a href="/user/<?= urlencode($user) ?>">
                    <span class="profile-thumb">
                        <span class="centering-wrap">
                            <?php
                            $profile_icon = 'img/no_videos_140.jpg';
                            if (!empty($user_data['profile_icon_custom'])) {
                                $profile_icon = $user_data['profile_icon_custom'];
                            } elseif (!empty($user_data['profile_icon']) && $user_data['profile_icon'] === '1') {
                                $stmtIcon = $db->prepare("SELECT profile_icon_custom FROM users WHERE login = ?");
                                $stmtIcon->execute([$user]);
                                $customIcon = $stmtIcon->fetchColumn();
                                if ($customIcon && is_string($customIcon) && $customIcon !== '') {
                                    $profile_icon = $customIcon;
                                }
                            } else {
                                $stmtLastVideo = $db->prepare("SELECT preview FROM videos WHERE user = ? AND private = 0 ORDER BY id DESC LIMIT 1");
                                $stmtLastVideo->execute([$user]);
                                $lastVideo = $stmtLastVideo->fetch(PDO::FETCH_ASSOC);
                                if ($lastVideo && !empty($lastVideo['preview'])) {
                                    $profile_icon = $lastVideo['preview'];
                                }
                            }
                            ?>
                            <img src="<?= htmlspecialchars($profile_icon, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8') ?>">
                        </span>
                    </span>
                </a>
                <div class="upper-left-section">
                    <h1><?= htmlspecialchars($user_data['username'] ?? $user, ENT_QUOTES, 'UTF-8') ?></h1>
                </div>

                <div class="upper-left-section enable-fancy-subscribe-button">
                    <?php
                    $is_subscribed = false;
                    if (isset($_SESSION['user']) && $_SESSION['user'] !== $user) {
                        try {
                            $stmtSub = $db->prepare("SELECT 1 FROM user_friends WHERE user = ? AND friend = ? LIMIT 1");
                            $stmtSub->execute([$_SESSION['user'], $user]);
                            $is_subscribed = (bool)$stmtSub->fetchColumn();
                        } catch (Exception $e) {
                            $is_subscribed = false;
                        }
                    }
                    ?>
                    <div class="yt-subscription-button-hovercard yt-uix-hovercard">
                        <?php if (!isset($_SESSION['user'])): ?>
                        <button onclick="window.location.href='login.php';" type="button" class="yt-subscription-button subscription-button-with-recommended-channels yt-uix-button yt-uix-button-subscription yt-uix-tooltip" role="button">
                            <span class="yt-uix-button-icon-wrapper"><img class="yt-uix-button-icon yt-uix-button-icon-subscribe" src="img/pixel.gif" alt=""></span>
                            <span class="yt-uix-button-content"><span class="subscribe-label">Subscribe</span></span>
                        </button>
                        <?php elseif ($is_subscribed): ?>
                        <button onclick="window.location.href='/user/<?= urlencode($user) ?>&unsubscribe=1';" type="button" class="yt-subscription-button subscription-button-with-recommended-channels yt-uix-button yt-uix-button-subscribed yt-uix-tooltip" role="button">
                            <span class="yt-uix-button-icon-wrapper"><img class="yt-uix-button-icon yt-uix-button-icon-subscribe" src="img/pixel.gif" alt=""></span>
                            <span class="yt-uix-button-content"><span class="subscribed-label">Subscribed</span></span>
                        </button>
                        <?php else: ?>
                        <form method="post" action="/user/<?= urlencode($user) ?>" style="display:inline; margin:0;">
                            <input type="hidden" name="add_friend" value="1">
                            <button type="submit" class="yt-subscription-button subscription-button-with-recommended-channels yt-uix-button yt-uix-button-subscription yt-uix-tooltip" role="button">
                                <span class="yt-uix-button-icon-wrapper"><img class="yt-uix-button-icon yt-uix-button-icon-subscribe" src="img/pixel.gif" alt=""></span>
                                <span class="yt-uix-button-content"><span class="subscribe-label">Subscribe</span></span>
                            </button>
                        </form>
                        <?php endif; ?>
                        <div class="yt-uix-hovercard-content hid">
                            <p class="loading-spinner"><img src="img/pixel.gif" alt="">Loading...</p>
                        </div>
                    </div>
                </div>
                <div class="upper-right-section">
                    <div class="header-stats">
                        <?php
                        $subscribers_count = 0;
                        try {
                            $stmtSubs = $db->prepare("SELECT COUNT(*) FROM user_friends WHERE friend = ?");
                            $stmtSubs->execute([$user]);
                            $subscribers_count = (int)$stmtSubs->fetchColumn();
                        } catch (Exception $e) {
                            $subscribers_count = 0;
                        }

                        $total_views = 0;
                        try {
                            $stmtViews = $db->prepare("SELECT SUM(views) AS total_views FROM videos WHERE user = ? AND private = 0");
                            $stmtViews->execute([$user]);
                            $total_views = (int)$stmtViews->fetchColumn();
                        } catch (Exception $e) {
                            $total_views = 0;
                        }

                        $video_count = 0;
                        try {
                            $stmtVidCnt = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
                            $stmtVidCnt->execute([$user]);
                            $video_count = (int)$stmtVidCnt->fetchColumn();
                        } catch (Exception $e) {
                            $video_count = 0;
                        }
                        ?>
                        <div class="stat-entry">
                            <span class="stat-value"><?= number_format($subscribers_count) ?></span>
                            <span class="stat-name">subscribers</span>
                        </div>

                        <div class="stat-entry">
                            <span class="stat-value"><?= number_format($total_views) ?></span>
                            <span class="stat-name">video views</span>
                        </div>
                    </div>
                    <span class="valign-shim"></span>
                </div>
            </div>
            <div class="channel-horizontal-menu clearfix">
                <ul>
                    <li class="<?= (!isset($_GET['tab']) || $_GET['tab'] === '' || $_GET['tab'] === 'featured') ? 'selected' : '' ?>">
                        <a href="/user/<?= urlencode($user) ?>&tab=featured" class="gh-tab-100">Featured</a>
                    </li>
                    <li class="<?= (isset($_GET['tab']) && $_GET['tab'] === 'feed') ? 'selected' : '' ?>">
                        <a href="/user/<?= urlencode($user) ?>&tab=feed" class="gh-tab-102">Feed</a>
                    </li>
                    <li class="<?= (isset($_GET['tab']) && $_GET['tab'] === 'videos') ? 'selected' : '' ?>">
                        <a href="/user/<?= urlencode($user) ?>&tab=videos&view=public" class="gh-tab-101">Videos <span class="video-count">(<?= $video_count ?>)</span></a>
                    </li>
                    <li class="<?= (isset($_GET['tab']) && $_GET['tab'] === 'comments') ? 'selected' : '' ?>">
                        <a href="/user/<?= urlencode($user) ?>&tab=comments" class="gh-tab-103">Comments</a>
                    </li>
                    <?php if (isset($_SESSION['user']) && $_SESSION['user'] === $user): ?>
                    <li class="<?= (isset($_GET['tab']) && $_GET['tab'] === 'private') ? 'selected' : '' ?>">
                        <a href="/user/<?= urlencode($user) ?>&tab=videos&view=private" class="gh-tab-104">Private Videos</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <form id="channel-search" action="results.php" method="GET">
                    <input type="hidden" name="search_user" value="<?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8') ?>">
                    <input name="search_query" type="text" maxlength="100" class="search-field label-input-label" placeholder="Search Channel" value="<?= htmlspecialchars($_GET['search_query'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <button class="search-btn" type="submit">
                        <span class="search-btn-content">Search</span>
                    </button>
                    <a class="search-dismiss-btn" href="/user/<?= urlencode($user) ?>">
                        <span class="search-btn-content">Clear</span>
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>
    <div id="branded-page-body">
        <div class="channel-tab-content channel-layout-two-column selected blogger-template">
            <div class="tab-content-body">
                <div class="primary-pane">
                    <?php if ($latest_public): ?>
                    <div class="channels-featured-video channel-module yt-uix-c3-module-container has-visible-edge">
                        <div class="module-view featured-video-view-module">
                            <div class="channels-video-player player-root" style="overflow: hidden;">
                                <div class="player-container">
                                    <div class="player-container">
                                        <a href="watch?v=<?= urlencode((string)($latest_public['public_id'] ?? $latest_public['id'])) ?>">
                                            <img src="<?= htmlspecialchars((string)($latest_public['preview'] ?? 'img/no_videos_140.jpg'), ENT_QUOTES, 'UTF-8') ?>" width="640" height="360" style="background:#000;">
                                        </a>
                                    </div>
                                </div>
                                <div class="player-actions-container">
                                    <div class="player-actions-share"></div>
                                    <div class="player-actions-close"><div class="player-actions-close-button"></div></div>
                                </div>
                            </div>
                            <div class="channels-featured-video-details yt-tile-visible clearfix">
                                <h3 class="title">
                                    <a href="watch?v=<?= urlencode((string)($latest_public['public_id'] ?? $latest_public['id'])) ?>">
                                        <?= htmlspecialchars((string)($latest_public['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <div class="view-count-and-actions">
                                        <div class="view-count">
                                            <span class="count"><?= (int)($latest_public['views'] ?? 0) ?></span> views
                                        </div>
                                    </div>
                                </h3>
                                <p class="channels-featured-video-metadata">
                                    <span>by <?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="created-date"><?= time_ago(strtotime($latest_public['time'] ?? 'now')) ?></span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="single-playlist channel-module yt-uix-c3-module-container">
                        <div class="module-view single-playlist-view-module">
                            <div class="blogger-playall">
                                <a class="yt-playall-link yt-playall-link-default" href="/user/<?= urlencode($user) ?>&tab=videos&view=public">
                                    <img class="small-arrow" src="img/pixel.gif" alt="">
                                    Play all
                                </a>
                            </div>
                            <div class="playlist-info">
                                <h2>Uploaded videos</h2>
                                <span class="blogger-video-count">1-<?= min(10, $public_count) ?> of <?= $public_count ?></span>
                                <div class="yt-horizontal-rule"><span class="first"></span><span class="second"></span><span class="third"></span></div>
                            </div>
                            <ul class="gh-single-playlist">
                                <?php
                                $stmt_videos = $db->prepare("SELECT id, public_id, title, preview, description, time, views, user, file, tags FROM videos WHERE user = ? AND private = 0 ORDER BY id DESC LIMIT 10");
                                $stmt_videos->execute([$user]);
                                $recent_videos = $stmt_videos->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($recent_videos as $video):
                                    $comments_cnt = 0;
                                    try {
                                        $stCc = $db->prepare("SELECT COUNT(*) FROM comments WHERE video_id = ?");
                                        $stCc->execute([$video['id']]);
                                        $comments_cnt = (int)$stCc->fetchColumn();
                                    } catch (Exception $e) {}
                                ?>
                                <li class="blogger-video">
                                    <div class="video yt-tile-visible">
                                        <a href="watch?v=<?= htmlspecialchars($video['public_id'] ?? $video['id']) ?>">
                                            <span class="ux-thumb-wrap contains-addto">
                                                <span class="video-thumb ux-thumb yt-thumb-default-288">
                                                    <span class="yt-thumb-clip">
                                                        <span class="yt-thumb-clip-inner">
                                                            <img src="<?= htmlspecialchars($video['preview'] ?? 'img/default.jpg') ?>" alt="Thumbnail" width="288">
                                                            <span class="vertical-align"></span>
                                                        </span>
                                                    </span>
                                                </span>
                                                <span class="video-time"><?= get_video_duration_fast($video['file'], $video['id'], $video['public_id'] ?? '') ?></span>
                                            </span>
                                            <span class="video-item-content">
                                                <span class="video-overview">
                                                    <span class="title video-title" title="<?= htmlspecialchars($video['title']) ?>"><?= htmlspecialchars($video['title']) ?></span>
                                                </span>
                                                <span class="video-details">
                                                    <span class="yt-user-name video-owner" dir="ltr"><?= htmlspecialchars($video['user']) ?></span>
                                                    <span class="video-view-count"><?= number_format($video['views']) ?> views</span>
                                                    <span class="video-time-published"><?= time_ago(strtotime($video['time'])) ?></span>
                                                    <span class="video-item-description"><?= htmlspecialchars(mb_substr($video['description'] ?? '', 0, 120)) ?>...</span>
                                                </span>
                                            </span>
                                        </a>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                                <?php if ($public_count > 10): ?>
                                <li class="video">
                                    <a href="/user/<?= urlencode($user) ?>&tab=videos&view=public" class="more-videos yt-uix-button yt-uix-button-default">
                                        <span class="yt-uix-button-content">Load <?= min(10, $public_count - 10) ?> more videos</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="secondary-pane">
                    <div class="user-profile channel-module yt-uix-c3-module-container">
                        <div class="module-view profile-view-module">
                            <h2>About <?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8') ?></h2>
                            <div class="section first">
                                <div class="user-profile-item profile-description">
                                    <div class="yt-uix-expander yt-uix-expander-collapsed yt-c3-expander">
                                        <div class="yt-uix-expander-body">
                                            <p><?= nl2br(htmlspecialchars($about_text ?: 'Нет описания', ENT_QUOTES, 'UTF-8')) ?></p>
                                            <button type="button" class="yt-uix-expander-head yt-uix-button yt-uix-button-link" onclick=";return false;" role="button"><span class="yt-uix-button-content"> less <img alt="" src="img/pixel.gif"></span></button>
                                        </div>
                                        <div class="yt-uix-expander-collapsed-body">
                                            <p><?= nl2br(htmlspecialchars(mb_substr($about_text ?: 'Нет описания', 0, 150), ENT_QUOTES, 'UTF-8')) ?>...</p>
                                            <button type="button" class="yt-uix-expander-head yt-uix-button yt-uix-button-link" onclick=";return false;" role="button"><span class="yt-uix-button-content"> more <img alt="" src="img/pixel.gif"></span></button>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($website_raw !== ''): ?>
                                <div class="user-profile-item">
                                    <div class="yt-c3-profile-custom-url field-container">
                                        <a href="<?= htmlspecialchars($website_href, ENT_QUOTES, 'UTF-8') ?>" rel="me nofollow" target="_blank">
                                            <img src="img/globe.gif" class="favicon" alt="">
                                            <span class="link-text"><?= htmlspecialchars($website_raw, ENT_QUOTES, 'UTF-8') ?></span>
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <hr class="yt-horizontal-rule">
                            </div>
                            <div class="section created-by-section">
                                <div class="user-profile-item">by <span class="yt-user-name" dir="ltr"><?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8') ?></span></div>
                                <div class="user-profile-item">
                                    <h5>Latest Activity</h5>
                                    <span class="value"><?= rus_date($last_login_time) ?></span>
                                </div>
                                <div class="user-profile-item">
                                    <h5>Date Joined</h5>
                                    <span class="value"><?= rus_date($signup_time) ?></span>
                                </div>
                            </div>
                            <div class="section">
                                <?php if (!empty($user_data['country'])): ?>
                                <div class="user-profile-item">
                                    <h5>Country</h5>
                                    <span class="value"><?= htmlspecialchars((string)$user_data['country'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <?php endif; ?>
                                <hr class="yt-horizontal-rule">
                            </div>
                        </div>
                    </div>

                    <div class="playlists-narrow channel-module yt-uix-c3-module-container">
                        <div class="module-view gh-featured">
                            <h2>Featured Playlists</h2>
                            <div class="playlist yt-tile-visible yt-uix-tile">
                                <a href="/user/<?= urlencode($user) ?>&tab=videos&view=public" class="play-all">
                                    <span class="playlist-thumb-strip playlist-thumb-strip-252">
                                        <span class="videos videos-4 horizontal-cutoff">
                                            <?php
                                            $thumb_videos = array_slice($recent_videos, 0, 4);
                                            foreach ($thumb_videos as $tv):
                                            ?>
                                            <span class="clip"><span class="centering-offset"><span class="centering"><span class="ie7-vertical-align-hack">&nbsp;</span><img src="<?= htmlspecialchars($tv['preview'] ?? 'img/default.jpg') ?>" alt="" class="thumb"></span></span></span>
                                            <?php endforeach; ?>
                                        </span>
                                        <span class="resting-overlay"><img src="img/play-icon-resting.png" class="play-button" alt="Play all"><span class="video-count-box"><?= $public_count ?> videos</span></span>
                                        <span class="hover-overlay"><span class="play-all-container"><strong><img src="img/mini-play-all.png" alt="">Play all</strong></span></span>
                                    </span>
                                </a>
                                <h3><a href="/user/<?= urlencode($user) ?>&tab=videos&view=public" class="yt-uix-tile-link">Uploaded videos</a></h3>
                                <span class="playlist-author-attribution">by <?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <a class="view-all-link" href="/user/<?= urlencode($user) ?>&tab=videos&view=public">view all <img src="img/pixel.gif" alt=""></a>
                        </div>
                    </div>

                    <?php
                    $featured_channels = [];
                    try {
                        $stmtFc = $db->prepare("SELECT friend FROM user_friends WHERE user = ? LIMIT 6");
                        $stmtFc->execute([$user]);
                        $featured_channels = $stmtFc->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
                    } catch (Exception $e) {}
                    if (!empty($featured_channels)):
                    ?>
                    <div class="channel-module other-channels yt-uix-c3-module-container other-channels-compact">
                        <div class="module-view other-channels-view">
                            <h2>Featured Channels</h2>
                            <ul class="channel-summary-list">
                                <?php foreach ($featured_channels as $fc):
                                    $fc_data = null;
                                    try {
                                        $stFcData = $db->prepare("SELECT login FROM users WHERE login = ?");
                                        $stFcData->execute([$fc]);
                                        $fc_data = $stFcData->fetch(PDO::FETCH_ASSOC);
                                    } catch (Exception $e) {}
                                    if (!$fc_data) continue;
                                    $fc_subscribers = 0;
                                    try {
                                        $stSubs = $db->prepare("SELECT COUNT(*) FROM user_friends WHERE friend = ?");
                                        $stSubs->execute([$fc]);
                                        $fc_subscribers = (int)$stSubs->fetchColumn();
                                    } catch (Exception $e) {}
                                ?>
                                <li class="yt-tile-visible yt-uix-tile">
                                    <div class="channel-summary clearfix channel-summary-compact">
                                        <div class="channel-summary-thumb">
                                            <span class="video-thumb ux-thumb yt-thumb-square-46">
                                                <span class="yt-thumb-clip">
                                                    <span class="yt-thumb-clip-inner">
                                                        <img src="<?= get_profile_icon($fc, get_user_profile_icon_setting($fc)) ?>" alt="Thumbnail" width="46">
                                                        <span class="vertical-align"></span>
                                                    </span>
                                                </span>
                                            </span>
                                        </div>
                                        <div class="channel-summary-info">
                                            <h3 class="channel-summary-title">
                                                <a href="/user/<?= urlencode($fc) ?>" class="yt-uix-tile-link"><?= htmlspecialchars($fc, ENT_QUOTES, 'UTF-8') ?></a>
                                            </h3>
                                            <span class="subscriber-count"><strong><?= number_format($fc_subscribers) ?></strong> subscribers</span>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    showFooter();
    exit;
}

showFooter();
?>