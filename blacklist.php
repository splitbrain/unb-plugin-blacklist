<?php
/*
Name:     Blacklist UNB Plugin
Purpose:  Check posts against a blacklist of regular expressions
Version:  2014-03-21
Author:   Andreas Gohr <andi@splitbrain.org>
*/
if (!defined('UNB_RUNNING')) die('Not a UNB environment in ' . basename(__FILE__));

// Define plug-in meta-data
UnbPluginMeta('Check posts against a blacklist of regular expressions');
UnbPluginMeta('Andreas Gohr <andi@splitbrain.org>', 'author');
UnbPluginMeta('en', 'lang');
UnbPluginMeta('unb.devel.20110527', 'version');

if (!UnbPluginEnabled()) return;

function plugin_blacklist_hook(&$data) {
    global $UNB_T;

    // get text from hook data
    $text = $data['body'];

    // we prepare the text a tiny bit to prevent spammers circumventing URL checks
    $text = preg_replace('!(\b)(www\.[\w.:?\-;,]+?\.[\w.:?\-;,]+?[\w/\#~:.?+=&%@\!\-.:?\-;,]+?)([.:?\-;,]*[^\w/\#~:.?+=&%@\!\-.:?\-;,])!i', '\1http://\2 \2\3', $text);

    // load blacklist
    $wordblocks = @file('blacklist-plugin.conf');

    // large blcklists need to be read in chunks to avoid running against
    // MAX_PATTERN_SIZE in PCRE
    while($blocks = array_splice($wordblocks, 0, 200)) {
        $re = array();
        // build regexp from blocks
        foreach($blocks as $block) {
            $block = preg_replace('/#.*$/', '', $block);
            $block = trim($block);
            if(empty($block)) continue;
            $re[] = $block;
        }
        if(count($re) && preg_match('#('.join('|', $re).')#si', $text, $matches)) {
            // we matched something!
            UnbAddLog('blacklist: blocked post because post contained: '.$matches[0]);
            $data['error'] = $UNB_T['blacklist block'];
            break;
        }
    }

    return true;
}

function plugin_blacklist_cp_category(&$data) {
    if (!UnbCheckRights('is_admin')) return true;

    $data[] = array(
        'title' => 'blacklist label',
        'link' => UnbLink('@cp', 'cat=blacklist', true),
        'cpcat' => 'blacklist'
    );

    return true;
}

function plugin_blacklist_cp_category_page(&$data) {
    global $UNB, $UNB_T;
    $TP =& $UNB['TP'];
    if ($data['cat'] != 'blacklist' || !UnbCheckRights('is_admin')) return true;

    // save and load
    if(isset($_POST['blacklist_list'])){
        @file_put_contents('blacklist-plugin.conf', $_POST['blacklist_list']);
    }
    $blacklist = @file_get_contents('blacklist-plugin.conf');

    // build form
    ob_start();
    echo '<form action="' . UnbLink('@this', 'cat=blacklist', true) . '" method="post">';
    echo '<textarea name="blacklist_list" cols="80" rows="15">'.htmlspecialchars($blacklist).'</textarea><br />';
    echo '<input type="submit" value="'.$UNB_T['blacklist save'].'">';
    echo '</form>';

    // setup control panel
    $TP['controlpanelMoreCats'][] = 'controlpanel_generic.html';
    $TP['GenericTitle'] = 'blacklist label';
    $TP['GenericContent'] = ob_get_clean();
    return true;
}

// Register hook functions
UnbRegisterHook('post.verifyaccept', 'plugin_blacklist_hook');
UnbRegisterHook('cp.addcategory', 'plugin_blacklist_cp_category');
UnbRegisterHook('cp.categorypage', 'plugin_blacklist_cp_category_page');
