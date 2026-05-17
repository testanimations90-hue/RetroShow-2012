<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function showHeader($title = "RetroShow") {
    global $show_menu, $db;
    if (!isset($show_menu)) $show_menu = true;
    $current = strtolower(basename($_SERVER['SCRIPT_NAME']));
    function nav_link($href, $text) {
        global $current;
        $is_active = ($current === strtolower($href));
		
        return $is_active
            ? '<span style="font-weight:bold; color:#0033cc;">'.$text.'</span>'
            : '<a href="'.$href.'">'.$text.'</a>';
    }
	function nav_link_ex($href, $text, $is_active) {
    return $is_active
        ? '<a href="'.$href.'"><b style="color:#0033cc;text-decoration:underline">'.$text.'</b></a>'
        : '<a href="'.$href.'">'.$text.'</a>';
}
	
?>
<html><head><style class="vjs-styles-defaults">
	.vjs-fluid:not(.vjs-audio-only-mode) {
		padding-top: 56.25%
	}
</style>

<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php if ($title === "Главная"): ?>
<title>RetroShow - Загружайте и делитесь видео по всему миру!</title>
<?php else: ?>
<title><?= htmlspecialchars($title) ?> - RetroShow</title>
<?php endif; ?>
		
		<script language="javascript" type="text/javascript">
		onLoadFunctionList = new Array();
		function performOnLoadFunctions()
		{
			for (var i in onLoadFunctionList)
			{
				onLoadFunctionList[i]();
			}
		}
		</script>
<base href="/">
<link rel="icon" href="favicon.ico" type="image/x-icon">
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
<meta name="description" content="Share your videos with friends and family">
<link rel="stylesheet" href="img/styles.css" type="text/css">
<link rel="stylesheet" href="img/base.css" type="text/css">
<link rel="stylesheet" href="img/yt2012.css" type="text/css">
<link rel="stylesheet" href="img/guide.css" type="text/css">
<link rel="stylesheet" href="img/watch.css" type="text/css">
<link rel="stylesheet" href="img/channels.css" type="text/css">
<script type="text/javascript" src="img/ui_ets.js"></script>
<link href="img/styles.css" rel="stylesheet" type="text/css">
<link rel="alternate" type="application/rss+xml" title="Recently Added Videos" href="rss.php">
<style type="text/css">
.formTitle { font-size: 16px; font-weight: bold; margin-bottom: 15px; color: #333; }
.error { background-color: #FFE6E6; border: 1px solid #FF9999; padding: 10px; margin: 10px 0px; color: #CC0000; font-size: 12px; }
.success { background-color: #E6FFE6; border: 1px solid #99FF99; padding: 10px; margin: 10px 0px; color: #006600; font-size: 12px; }
.formTable { margin: 0px auto; }
.label { font-weight: bold; color: #333; font-size: 12px; }
.pageTitle { font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #333; }
.pageIntro { font-size: 14px; margin-bottom: 15px; color: #333; line-height: 1.4; }
.pageText { font-size: 12px; margin-bottom: 15px; color: #333; line-height: 1.4; }
.codeArea { background-color: #F5F5F5; border: 1px solid #CCCCCC; padding: 10px; margin: 10px 0px; font-family: monospace; font-size: 11px; color: #333; }
.pagingDiv { text-align: center; margin: 15px 0px; font-size: 12px; }
.pagerCurrent { background-color: #CCCCCC; border: 1px solid #999999; padding: 3px 8px; margin: 0px 2px; font-weight: bold; }
.pagerNotCurrent { background-color: #FFFFFF; border: 1px solid #CCCCCC; padding: 3px 8px; margin: 0px 2px; cursor: pointer; text-decoration: none; color: #000000; }
#footerDiv {
	clear: both;
	margin: 12px auto 24px auto;
	padding-bottom: 12px;
	font-size: 11px;
}
#footerCopyright { padding-top: 12px; text-align: center; }
#footerSearch { padding-top: 8px; text-align: center; }
#footerLinks { height: 66px; line-height: 15px; }
#footerContent {
	background: #EEE;
	border-top: 1px solid #CCC;
	border-bottom: 1px solid #CCC;
	padding: 8px 0px;
}
.footColumn { }
.footColumnBar {
	height: 60px;
	width: 170px;
	margin-right: 20px;
}
.footLabel {
	font-weight: bold;
	font-size: 11px;
	color: #333;
}
.footValues {
	margin-left: 0px;
	padding-bottom: 6px;
	font-size: 11px;
}
.footValues .column { float: left; padding-right: 26px; }

.hpStatsHeading {
	font-weight: bold;
	font-size: 13px;
	margin-bottom: 2px;
}

.smallLabel {
	font-weight: bold;
	font-size: 11px;
	color: #333;
}
</style>
<!--[if lt IE 6]>
<style type="text/css">
#ratingMessage { display:none; }
</style>
<![endif]-->
<!--[if lte IE 6]>
<style type="text/css">
html, body {
	margin: 10px !important;
	padding: 0 !important;
}
.showingTable {
	padding: 8px 6px !important;
	margin: 0 !important;
}
.showingTable td {
	padding-top: 4px !important;
	padding-bottom: 4px !important;
    padding-left: 6px !important;
	line-height: 16px !important;
	vertical-align: middle !important;
}
</style>
<![endif]-->
</head>


<body onload="performOnLoadFunctions();">
 
<div id="masthead-container">
    <!-- begin masthead -->
      <div id="masthead" class="" dir="ltr">
          <a id="logo-container" href="/" title="YouTube home">
    <img id="logo" src="/yt/img/pixel-vfl3z5WfW.gif" alt="YouTube home">
  </a>


    <div id="masthead-user-bar-container">
      <div id="masthead-user-bar">
        <div id="masthead-user">
          <div id="masthead-user-display">
            <?php if (!isset($_SESSION['user'])): ?>
              <a class="start" href="register.php?feature=header&amp;next=%2F">Create Account</a>
              <span class="masthead-link-separator">|</span>
              <a class="end" href="login.php">Sign In</a>
            <?php else: ?>
              Привет, <a href="/user/<?=urlencode($_SESSION['user'])?>"><?=htmlspecialchars($_SESSION['user'])?></a>!
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <div id="masthead-search-bar-container">
      <div id="masthead-search-bar">
        <div id="masthead-nav">
          <a href="browse">Browse</a>
          <span class="masthead-link-separator">|</span>
          <a href="index.php">Movies</a>                
          <span class="masthead-link-separator">|</span>
          <a id="masthead-upload-link" class="" data-upsell="upload" href="upload.php">Upload</a>
        </div>        

        <form id="masthead-search" class="search-form consolidated-form" action="results.php" onsubmit="if (_gel('masthead-search-term').value == '') return false;">
          <button class="search-btn-compontent search-button yt-uix-button yt-uix-button-default" onclick="if (_gel('masthead-search-term').value == '') return false; _gel('masthead-search').submit(); return false;;return true;" type="submit" id="search-btn" dir="ltr" tabindex="2" role="button"><span class="yt-uix-button-content">Search </span></button>
          <div id="masthead-search-terms" class="masthead-search-terms-border " dir="ltr">
            <label><input id="masthead-search-term" autocomplete="off" class="search-term" name="search_query" value="<?=htmlspecialchars($_GET['search_query'] ?? '')?>" type="text" tabindex="1" onkeyup="goog.i18n.bidi.setDirAttribute(event,this)" title="Search" dir="ltr" spellcheck="false" style="outline: none;"></label>
          </div>  
          <input type="hidden" name="oq"><input type="hidden" name="gs_l">
        </form>

      </div>
    </div>
  </div>
  <div id="content">
  <div id="masthead_child_div">
   <div id="alerts"></div>
<?php
$news_file = __DIR__ . '/news.txt';
if (file_exists($news_file)) {
    $news_text = trim(file_get_contents($news_file));
    if (!empty($news_text)) {
        echo '<div class="yt-alert yt-alert-default yt-alert-error  yt-alert-player"><div class="yt-alert-icon"><img src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yt/img/pixel-vfl3z5WfW.gif" class="icon master-sprite" alt="Alert icon"></div><div class="yt-alert-buttons"></div><div class="yt-alert-content" role="alert">    <span class="yt-alert-vertical-trick"></span>
    <div class="yt-alert-message"><div class="yt-alert-message">' . nl2br($news_text) . '</div>    </div>
</div></div>';
    }
}
?>

    <!-- end masthead -->
  </div>

<div style="padding: 0px 5px 0px 5px;">
<center>
<center>
<?php }
function showFooter() {
?>
<div id="footer">
      <div class="yt-horizontal-rule "><span class="first"></span><span class="second"></span><span class="third"></span></div>

    <div id="footer-logo">
      <a href="/" title="YouTube home">
        <img src="/yt/img/pixel-vfl3z5WfW.gif" alt="YouTube home">
      </a>
        <span class="copyright" dir="ltr">© 2026 RetroShow, LLC</span>

      <span id="footer-divider"></span>
    </div>
    <div id="footer-main">
        
  <div id="in-product-help" class="yt-uix-clickcard">
    <button type="button" id="help-button" onclick=";return false;" class="yt-uix-clickcard-target yt-uix-button-reverse yt-uix-button yt-uix-button-default" data-orientation="vertical" data-locale="en_US" data-iph-anchor-text="More Help" data-iph-search-button-text="Search" data-iph-tracking="iph-questionmark" data-iph-js-url="//s.ytimg.com/yt/jsbin/www-help-vflEQIkje.js" data-iph-search-input-text="Search YouTube's Help Center" data-iph-title-text="Need Help on this page?" data-iph-topic-id="1699306" data-iph-css-url="//s.ytimg.com/yt/cssbin/www-helpie-vflm_C0iz.css" data-help-center-host="//support.google.com/youtube" role="button"><span class="yt-uix-button-content">  <img class="questionmark" src="/yt/img/pixel-vfl3z5WfW.gif">
  <span>Help</span>
  <img class="yt-uix-button-arrow" src="/yt/img/pixel-vfl3z5WfW.gif">
 </span></button>
    <div class="yt-uix-clickcard-content" id="help-target">  <p class="loading-spinner">
    <img src="/yt/img/pixel-vfl3z5WfW.gif" alt="">
Loading...
  </p>
</div>
  </div>

      <ul id="footer-links-primary">
        <li><a href="/web/20120630034132/http://www.youtube.com/t/about_youtube">About</a></li>
        <li><a href="/web/20120630034132/http://www.youtube.com/t/press">Press &amp; Blogs</a></li>
        <li><a href="/web/20120630034132/http://www.youtube.com/t/copyright_center">Copyright</a></li>
        <li><a href="/web/20120630034132/http://www.youtube.com/creators">Creators &amp; Partners</a></li>
        <li><a href="/web/20120630034132/http://www.youtube.com/t/advertising_overview">Advertising</a></li>
        <li><a href="/web/20120630034132/http://www.youtube.com/dev">Developers</a></li>
      </ul>


      <ul id="footer-links-secondary">
        <li><a href="/web/20120630034132/http://www.youtube.com/t/terms">Terms</a></li>
        <li><a href="https://web.archive.org/web/20120630034132/http://www.google.com/intl/en/policies/privacy/">Privacy</a></li>
        <li><a href="//web.archive.org/web/20120630034132/http://support.google.com/youtube/bin/request.py?contact_type=abuse&amp;hl=en-US">Safety</a></li>
        <li><a href="//web.archive.org/web/20120630034132/http://www.google.com/tools/feedback/intl/en/error.html" onclick="return yt.www.feedback.start(yt.getConfig('FEEDBACK_LOCALE_LANGUAGE'), yt.getConfig('FEEDBACK_LOCALE_EXTRAS'));" id="reportbug">Report a bug</a></li>
        <li><a href="/web/20120630034132/http://www.youtube.com/testtube">Try something new!</a></li>
      </ul>
        <ul class="pickers yt-uix-button-group" data-button-toggle-group="optional">
      <li>
Language:
          <button type="button" class="yt-uix-button yt-uix-button-text" onclick="yt.www.picker.load(&quot;language&quot;, &quot;footer&quot;);return false;" data-button-toggle="true" data-button-menu-id="arrow-display" role="button"><span class="yt-uix-button-content">English </span><img class="yt-uix-button-arrow" src="/yt/img/pixel-vfl3z5WfW.gif" alt=""></button>

      </li>
      <li>
Location:
          <button type="button" class="yt-uix-button yt-uix-button-text" onclick="yt.www.picker.load(&quot;country&quot;, &quot;footer&quot;);return false;" data-button-toggle="true" data-button-menu-id="arrow-display" role="button"><span class="yt-uix-button-content">Worldwide </span><img class="yt-uix-button-arrow" src="/yt/img/pixel-vfl3z5WfW.gif" alt=""></button>

      </li>
      <li>
Safety:
          <button type="button" class="yt-uix-button yt-uix-button-text" onclick="yt.www.picker.load(&quot;safetymode&quot;, &quot;footer&quot;);return false;" data-button-toggle="true" data-button-menu-id="arrow" role="button"><span class="yt-uix-button-content">Off
 </span><img class="yt-uix-button-arrow" src="/yt/img/pixel-vfl3z5WfW.gif" alt=""></button>

      </li>
  </ul>
        <div id="yt-picker-language-footer" class="yt-picker hid" style="display: none;" data-loaded="1">      <div class="yt-picker-header">
    <button class="yt-close yt-uix-close" data-close-parent-class="yt-picker">
      <img class="yt-close-img" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" alt="Close">
    </button>
    <h3 class="yt">Choose your language</h3>
      <p class="yt-notes">Choose the language in which you want to view YouTube. This will only change the interface, not any text entered by other users.</p>
  </div>
  <div class="yt-picker-content yt-grid-inline">
      <form action="/web/20120630034132mp_/http://www.youtube.com/picker_ajax?action_update_language=1" method="POST">
    <input type="hidden" name="persist_hl" value="1">
    <input type="hidden" name="base_url" value="/">
    <input type="hidden" name="session_token" value="DBoNG3-U4Uy_jSdW0IzM-FLv3Cl8MTM1NjE2NzE5OEAxMzU2MDgwNzk4">
    <div class="yt-picker-section"></div>
  </form>

  </div>


</div>

      <div id="yt-picker-country-footer" class="yt-picker hid" style="display: none;" data-loaded="1">      <div class="yt-picker-header">
    <button class="yt-close yt-uix-close" data-close-parent-class="yt-picker">
      <img class="yt-close-img" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" alt="Close">
    </button>
    <h3 class="yt">Choose your content location</h3>
      <p class="yt-notes">Choose which country or region's content (videos and channels) you would like to view. This will not change the language of the site.</p>
  </div>
  <div class="yt-picker-content yt-grid-inline">
      <div class="yt-picker-section"><div class="yt-picker-grid"><strong class="yt-picker-item">
    <img id="flag_en_US" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_en_US" alt="">
    Worldwide (All)
  </strong></div></div>
  <hr class="yt-picker-hr">
  <div class="yt-picker-section"><div class="yt-picker-grid"><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=DZ">
    <img id="flag_ar_DZ" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_ar_DZ" alt="">
    Algeria
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=AR">
    <img id="flag_es_AR" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_es_AR" alt="">
    Argentina
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=AU">
    <img id="flag_en_AU" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_en_AU" alt="">
    Australia
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=BE">
    <img id="flag_en_BE" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_en_BE" alt="">
    Belgium
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=BR">
    <img id="flag_pt_BR" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_pt_BR" alt="">
    Brazil
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=CA">
    <img id="flag_en_CA" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_en_CA" alt="">
    Canada
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=CL">
    <img id="flag_es_CL" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_es_CL" alt="">
    Chile
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=CO">
    <img id="flag_es_CO" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_es_CO" alt="">
    Colombia
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=CZ">
    <img id="flag_cs_CZ" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_cs_CZ" alt="">
    Czech Republic
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=EG">
    <img id="flag_ar_EG" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_ar_EG" alt="">
    Egypt
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=FR">
    <img id="flag_fr_FR" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_fr_FR" alt="">
    France
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=DE">
    <img id="flag_de_DE" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_de_DE" alt="">
    Germany
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=GH">
    <img id="flag_en_GH" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_en_GH" alt="">
    Ghana
  </a></div><div class="yt-picker-grid"><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=GR">
    <img id="flag_el_GR" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_el_GR" alt="">
    Greece
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=HK">
    <img id="flag_zh_HK" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_zh_HK" alt="">
    Hong Kong
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=HU">
    <img id="flag_hu_HU" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_hu_HU" alt="">
    Hungary
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=IN">
    <img id="flag_en_IN" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_en_IN" alt="">
    India
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=ID">
    <img id="flag_id_ID" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_id_ID" alt="">
    Indonesia
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=IE">
    <img id="flag_en_IE" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_en_IE" alt="">
    Ireland
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=IL">
    <img id="flag_en_IL" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_en_IL" alt="">
    Israel
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=IT">
    <img id="flag_it_IT" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_it_IT" alt="">
    Italy
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=JP">
    <img id="flag_ja_JP" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_ja_JP" alt="">
    Japan
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=JO">
    <img id="flag_ar_JO" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_ar_JO" alt="">
    Jordan
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=KE">
    <img id="flag_en_KE" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_en_KE" alt="">
    Kenya
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=MY">
    <img id="flag_ms_MY" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_ms_MY" alt="">
    Malaysia
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=MX">
    <img id="flag_es_MX" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_es_MX" alt="">
    Mexico
  </a></div><div class="yt-picker-grid"><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=MA">
    <img id="flag_ar_MA" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_ar_MA" alt="">
    Morocco
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=NL">
    <img id="flag_nl_NL" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_nl_NL" alt="">
    Netherlands
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=NZ">
    <img id="flag_en_NZ" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_en_NZ" alt="">
    New Zealand
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=NG">
    <img id="flag_en_NG" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_en_NG" alt="">
    Nigeria
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=PE">
    <img id="flag_es_PE" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_es_PE" alt="">
    Peru
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=PH">
    <img id="flag_fil_PH" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_fil_PH" alt="">
    Philippines
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=PL">
    <img id="flag_pl_PL" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_pl_PL" alt="">
    Poland
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=RU">
    <img id="flag_ru_RU" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_ru_RU" alt="">
    Russia
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=SA">
    <img id="flag_ar_SA" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_ar_SA" alt="">
    Saudi Arabia
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=SN">
    <img id="flag_fr_SN" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_fr_SN" alt="">
    Senegal
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=SG">
    <img id="flag_en_SG" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_en_SG" alt="">
    Singapore
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=ZA">
    <img id="flag_en_ZA" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_en_ZA" alt="">
    South Africa
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=KR">
    <img id="flag_ko_KR" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_ko_KR" alt="">
    South Korea
  </a></div><div class="yt-picker-grid"><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=ES">
    <img id="flag_es_ES" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_es_ES" alt="">
    Spain
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=SE">
    <img id="flag_sv_SE" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_sv_SE" alt="">
    Sweden
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=TW">
    <img id="flag_zh_TW" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_zh_TW" alt="">
    Taiwan
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=TN">
    <img id="flag_ar_TN" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_ar_TN" alt="">
    Tunisia
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=TR">
    <img id="flag_tr_TR" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_tr_TR" alt="">
    Turkey
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=UG">
    <img id="flag_en_UG" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_en_UG" alt="">
    Uganda
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=UA">
    <img id="flag_uk_UA" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_uk_UA" alt="">
    Ukraine
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=AE">
    <img id="flag_ar_AE" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_ar_AE" alt="">
    United Arab Emirates
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=GB">
    <img id="flag_en_GB" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_en_GB" alt="">
    United Kingdom
  </a><a class="yt-picker-item" href="https://web.archive.org/web/20120630034132mp_/http://www.youtube.com/?persist_gl=1&amp;gl=YE">
    <img id="flag_ar_YE" src="https://web.archive.org/web/20120630034132im_///s.ytimg.com/yts/img/pixel-vfl3z5WfW.gif" width="17" height="11" class="flag_ar_YE" alt="">
    Yemen
  </a></div></div>

  </div>


</div>

      <div id="yt-picker-safetymode-footer" class="yt-picker hid">
      <p class="yt-spinner">
    <img src="/yt/img/pixel-vfl3z5WfW.gif" class="yt-spinner-img" alt="">
Loading...
  </p>

  </div>



    </div>
  </div>
    </div>
    </div>
</body>
</html>
<?php } ?>