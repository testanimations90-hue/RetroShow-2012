<?php
include 'init.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$me = (string)$_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_mail'])) {
    $del_id = (int) ($_POST['mail_id'] ?? 0);
    if ($del_id > 0) {
        try {
            $st = $db->prepare('DELETE FROM mail_inbox WHERE id = ? AND from_user = ?');
            $st->execute([$del_id, $me]);
        } catch (Exception $e) {
        }
    }
    header('Location: outbox.php');
    exit;
}

include 'template.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$msg = null;
if ($id > 0) {
    try {
        $st = $db->prepare('SELECT id, to_user, topic, content, sent_at, kind, comment_id, video_id, video_public_id, channel_login FROM mail_inbox WHERE id = ? AND from_user = ?');
        $st->execute([$id, $me]);
        $msg = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
    }
}

if (!$msg) {
    header('Location: outbox.php');
    exit;
}

$to = (string) $msg['to_user'];
$to_h = htmlspecialchars($to, ENT_QUOTES, 'UTF-8');
$body_h = htmlspecialchars((string) $msg['content'], ENT_QUOTES, 'UTF-8');
$topic_raw = trim((string) ($msg['topic'] ?? ''));
$topic_h = htmlspecialchars($topic_raw !== '' ? $topic_raw : mail_list_preview((string) $msg['content']), ENT_QUOTES, 'UTF-8');

$months_ru = [
    1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
    5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
    9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
];
$ts = (int) $msg['sent_at'];
$sent_str = (int) date('j', $ts) . ' ' . $months_ru[(int) date('n', $ts)] . ' ' . date('Y', $ts)
    . ', ' . date('H:i', $ts);

showHeader('Сообщение');
?>
<div class="tableSubTitle">Исходящие сообщения</div>
<table width="45%" align="center" cellpadding="5" cellspacing="0" border="0">
    <tr align="center">
        <td align="center" colspan="3">
            <a href="my_messages.php">Входящие сообщения</a> | <a href="outbox.php" class="bold">Исходящие сообщения</a>
        </td>
    </tr>
</table>

<table width="100%" align="center" cellpadding="1" cellspacing="1" border="0" bgcolor="#EEEEEE">
<tbody>
<tr>
	<td width="100%"><img src="img/pixel.gif" width="1" height="5" alt=""></td>
</tr>
<tr>
	<td>
	<table width="75%" cellpadding="4" cellspacing="9" align="center" border="0">
	<tbody>
	<tr><td colspan="2" height="10" width="35" align="right"></td></tr>
	<tr valign="top">
		<td align="right" valign="top"><span class="label">Кому:</span></td>
		<td><a href="channel.php?user=<?= rawurlencode($to) ?>"><?= $to_h ?></a></td>
	</tr>
	<tr valign="top">
		<td align="right" valign="top"><span class="label">Отправлено:</span></td>
		<td><?= htmlspecialchars($sent_str, ENT_QUOTES, 'UTF-8') ?></td>
	</tr>
	<tr valign="top">
		<td align="right" valign="top"><span class="label">Тема:</span></td>
		<td><?= $topic_h ?></td>
	</tr>
	<tr valign="top">
		<td align="right" valign="top"><span class="label">Сообщение:</span></td>
		<td><div class="mailMessageArea"><?= nl2br($body_h) ?></div></td>
	</tr>
	<tr valign="top">
		<td align="right" valign="top"></td>
		<td><form method="post" action="sent_msg.php" onsubmit="return confirm('Подтвердите: сообщение будет удалено без возможности восстановления.');">
			<input type="hidden" name="delete_mail" value="1">
			<input type="hidden" name="mail_id" value="<?= (int) $id ?>">
			<input type="submit" value="Удалить сообщение" style="width:135px;">
		</form></td>
	</tr>
	</tbody>
	</table>
	</td>
</tr>
<tr>
	<td><img src="img/pixel.gif" width="1" height="5" alt=""></td>
</tr>
</tbody>
</table>
<?php
showFooter();
?>

