<?php
include 'init.php';
include 'template.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$me = (string)$_SESSION['user'];
$to_user = isset($_GET['user']) ? trim((string)$_GET['user']) : '';
$is_compose = ($to_user !== '');

$months_ru = [
    1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
    5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
    9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
];
$days_ru = ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'];

$total = 0;
try {
    $st = $db->prepare('SELECT COUNT(*) FROM mail_inbox WHERE from_user = ?');
    $st->execute([$me]);
    $total = (int)$st->fetchColumn();
} catch (Exception $e) {
    $total = 0;
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 5;
$total_pages = $total > 0 ? (int)ceil($total / $per_page) : 1;
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

$rows = [];
try {
    $lim = (int)$per_page;
    $off = (int)$offset;
    $st = $db->prepare("SELECT id, to_user, topic, content, sent_at FROM mail_inbox WHERE from_user = ? ORDER BY sent_at DESC, id DESC LIMIT {$lim} OFFSET {$off}");
    $st->execute([$me]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $rows = [];
}

$msg = '';
$ok = false;
if ($is_compose && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $topic = trim((string)($_POST['title'] ?? ''));
    $body = trim((string)($_POST['comment'] ?? ''));

    if ($topic === '' && $body === '') {
        $msg = 'Введите тему или текст сообщения.';
    } elseif (strlen($topic) > 200) {
        $msg = 'Тема слишком длинная.';
    } elseif (strlen($body) > 5000) {
        $msg = 'Сообщение слишком длинное.';
    } else {
        try {
            $stU = $db->prepare('SELECT 1 FROM users WHERE login = ? LIMIT 1');
            $stU->execute([$to_user]);
            $exists = (bool)$stU->fetchColumn();
        } catch (Exception $e) {
            $exists = false;
        }
        if (!$exists) {
            $msg = 'Пользователь не найден.';
        } elseif ($to_user === $me) {
            $msg = 'Нельзя отправить сообщение самому себе.';
        } else {
            add_mail($db, $to_user, $me, $topic, $body, 'user_message', null, null, null, $to_user);
            $ok = true;
            $msg = 'Сообщение отправлено.';
        }
    }
}

showHeader('Сообщения');
?>
<style>
.channelPagingDiv { background: #CCC; margin: 0; padding: 5px 0; font-size: 13px; color: #333; font-weight: bold; text-align: right; }
.channelPagingDiv .pagerCurrent { color: #333; background-color: #FFF; padding: 1px 4px; border: 1px solid #999; margin-right: 5px; cursor: pointer; }
.channelPagingDiv .pagerNotCurrent { color: #03C; background-color: #CCC; padding: 1px 4px; border: 1px solid #999; margin-right: 5px; text-decoration: underline; cursor: pointer; }
.channelPagingDiv .pagerNotCurrent a { color: #03C; text-decoration: underline; }
</style>


    <div class="tableSubTitle"><?= $is_compose ? 'Исходящие сообщения' : 'Исходящие сообщения' ?></div>
    <table width="45%" align="center" cellpadding="5" cellspacing="0" border="0">
        <tr align="center">
            <td align="center" colspan="3">
                <a href="my_messages.php">Входящие</a> | <a href="outbox.php" class="bold">Исходящие</a>
            </td>
        </tr>
    </table>

    <?php if ($msg !== ''): ?>
        <div class="<?= $ok ? 'confirmBox' : 'errorBox' ?>" style="margin-bottom:8px;">
            <?=htmlspecialchars($msg)?>
        </div>
    <?php endif; ?>

    <?php if ($is_compose): ?>
        <table width="100%" align="center" cellpadding="1" cellspacing="1" border="0" bgcolor="#EEEEEE">
            <tr>
                <td width="100%"><img src="img/pixel.gif" width="1" height="5" alt=""></td>
            </tr>
            <tr>
                <td>
                    <form method="post" action="outbox.php?user=<?=urlencode($to_user)?>">
                        <table width="75%" cellpadding="4" cellspacing="9" align="center">
                            <tr>
                                <td align="right"><span class="label">Кому:</span></td>
                                <td><input type="text" size="50" value="<?=htmlspecialchars($to_user)?>" disabled></td>
                            </tr>
                            <tr>
                                <td align="right"><span class="label">Отправлено:</span></td>
                                <td><?=htmlspecialchars(date('d.m.Y, H:i'))?></td>
                            </tr>
                            <tr>
                                <td align="right"><span class="label">Тема:</span></td>
                                <td><input type="text" size="50" name="title" value="<?=htmlspecialchars((string)($_POST['title'] ?? ''))?>"></td>
                            </tr>
                            <tr>
                                <td align="right"><span class="label">Сообщение:</span></td>
                                <td><textarea name="comment" cols="66" rows="6"><?=htmlspecialchars((string)($_POST['comment'] ?? ''))?></textarea></td>
                            </tr>
                            <tr>
                                <td></td>
                                <td><input type="submit" name="message" value="Отправить сообщение"></td>
                            </tr>
                        </table>
                    </form>
                </td>
            </tr>
        </table>
    <?php else: ?>
        <table width="91%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#CCCCCC">
            <tr>
                <td><img src="img/box_login_tl.gif" width="5" height="5" alt=""></td>
                <td width="100%"><img src="img/pixel.gif" width="1" height="5" alt=""></td>
                <td><img src="img/box_login_tr.gif" width="5" height="5" alt=""></td>
            </tr>
            <tr>
                <td><img src="img/pixel.gif" width="5" height="1" alt=""></td>
                <td>
                    <div class="moduleTitleBar">
                        <div class="moduleTitle"><div style="float: right; padding: 1px 5px 0px 0px; font-size: 12px;">Сообщения <?= $total ? ($offset + 1) . '-' . min($offset + $per_page, $total) . ' из ' . $total : '0 из 0' ?></div>
                            Сообщения // Исходящие сообщения
                        </div>
                    </div>
                    <table width="100%" cellpadding="3" cellspacing="0" align="center" border="0">
                        <tbody>
                        <tr><td colspan="5" height="10"></td></tr>
                        <tr>
                            <td width="20">&nbsp;</td>
                            <td><b>Сообщение</b></td>
                            <td width="20">&nbsp;</td>
                            <td width="70"><b>Кому</b></td>
                            <td width="160"><b>Дата</b></td>
                        </tr>
                        <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="5" style="padding: 10px; text-align: center; background: #fff;">
                                У вас нет отправленных сообщений.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($rows as $row):
                            $mid = (int)($row['id'] ?? 0);
                            $to = (string)($row['to_user'] ?? '');
                            $to_h = htmlspecialchars($to, ENT_QUOTES, 'UTF-8');
                            $topic_raw = trim((string)($row['topic'] ?? ''));
                            $preview = $topic_raw !== '' ? mail_list_preview($topic_raw) : mail_list_preview((string)($row['content'] ?? ''));
                            $preview_h = htmlspecialchars($preview, ENT_QUOTES, 'UTF-8');
                            $ts = (int)($row['sent_at'] ?? time());
                            $date_str = $days_ru[(int)date('w', $ts)] . ', ' . (int)date('j', $ts) . ' ' . $months_ru[(int)date('n', $ts)] . ' ' . date('Y', $ts);
                            $href = 'read_msg.php?id=' . $mid . '&sent=1';
                        ?>
                        <tr bgcolor="#eeeeee">
                            <td width="5"><img src="img/mail.gif" alt=""></td>
                            <td><a href="<?=htmlspecialchars($href, ENT_QUOTES, 'UTF-8')?>"><?= $preview_h ?></a></td>
                            <td width="20">&nbsp;</td>
                            <td><a href="channel.php?user=<?=rawurlencode($to)?>"><?= $to_h ?></a></td>
                            <td><?= htmlspecialchars($date_str, ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if ($total_pages > 1): ?>
                    <div class="channelPagingDiv pagingDiv">
                        Стр.
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        if ($start_page > 1) {
                            echo '<span class="pagerNotCurrent"><a href="?page=1">1</a></span>';
                            if ($start_page > 2) echo ' ... ';
                        }
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $page) {
                                echo '<span class="pagerCurrent">'.$i.'</span>';
                            } else {
                                echo '<span class="pagerNotCurrent"><a href="?page='.$i.'">'.$i.'</a></span>';
                            }
                        }
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) echo ' ... ';
                            echo '<span class="pagerNotCurrent"><a href="?page='.$total_pages.'">'.$total_pages.'</a></span>';
                        }
                        if ($page < $total_pages) {
                            echo '<span class="pagerNotCurrent"><a href="?page='.($page + 1).'">Далее</a></span>';
                        }
                        ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td><img src="img/pixel.gif" width="5" height="1" alt=""></td>
            </tr>
            <tr>
                <td><img src="img/box_login_bl.gif" width="5" height="5" alt=""></td>
                <td><img src="img/pixel.gif" width="1" height="5" alt=""></td>
                <td><img src="img/box_login_br.gif" width="5" height="5" alt=""></td>
            </tr>
        </table>
    <?php endif; ?>
</div>

<?php showFooter(); ?>

