<?php
/**
 * Dizkus
 *
 * @copyright (c) 2001-now, Dizkus Development Team
 * @link http://www.dizkus.com
 * @version $Id$
 * @license GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package Dizkus
 */

Loader::includeOnce('modules/Dizkus/common.php');

/**
 * reply
 *
 */
function Dizkus_ajax_reply()
{
    $dom = ZLanguage::getModuleDomain('Dizkus');

    if (dzk_available(false) == false) {
       dzk_ajaxerror(strip_tags(pnModGetVar('Dizkus', 'forum_disabled_info')));
    }

    $topic_id         = FormUtil::getPassedValue('topic');
    $message          = FormUtil::getPassedValue('message', '');
    $title            = FormUtil::getPassedValue('title', '');
    $attach_signature = FormUtil::getPassedValue('attach_signature');
    $subscribe_topic  = FormUtil::getPassedValue('subscribe_topic');
    $preview          = FormUtil::getPassedValue('preview', 0);
    $preview          = ($preview=='1') ? true : false;

    SessionUtil::setVar('pn_ajax_call', 'ajax');

    $message = dzkstriptags(DataUtil::convertFromUTF8($message));
    $title   = dzkstriptags(DataUtil::convertFromUTF8($title));

	// ContactList integration: Is the user ignored and allowed to write an answer to this topic?
	$topic = DBUtil::selectObjectByID('dizkus_topics', $topic_id, 'topic_id');
	$topic['start'] = 0;
	$ignorelist_setting = pnModAPIFunc('Dizkus', 'user', 'get_settings_ignorelist', array('uid' => $topic['topic_poster']));
	if (pnModAvailable('ContactList') && ($ignorelist_setting == 'strict') && (pnModAPIFunc('ContactList', 'user', 'isIgnored', array('uid' => (int)$topic['topic_poster'], 'iuid' => pnUserGetVar('uid'))))) {
		dzk_ajaxerror(__('Sorry - the user who started this topic is ignoring you and does not want that you are able to write replies into this topic. Please contact the topic starter for more details.', $dom));
	}

    // check for maximum message size
    if ((strlen($message) + 8/*strlen('[addsig]')*/) > 65535) {
        dzk_ajaxerror(__('Illegal message size, max. 65535 chars', $dom));
    }

    if ($preview == false) {
        if (!SecurityUtil::confirmAuthKey()) {
           dzk_ajaxerror(__('Invalid \'authkey\':  this probably means that you pressed the \'Back\' button, or that the page \'authkey\' expired. Please refresh the page and try again.', $dom));
        }

        list($start,
             $post_id) = pnModAPIFunc('Dizkus', 'user', 'storereply',
                                       array('topic_id'         => $topic_id,
                                             'message'          => $message,
                                             'attach_signature' => $attach_signature,
                                             'subscribe_topic'  => $subscribe_topic,
                                             'title'            => $title));

        $topic['start'] = $start;
        $post = pnModAPIFunc('Dizkus', 'user', 'readpost',
                             array('post_id' => $post_id));

    } else {
        // preview == true, create fake post
        $post['post_id']         = 0;
        $post['topic_id']        = $topic_id;
        $post['poster_data']     = pnModAPIFunc('Dizkus', 'user', 'get_userdata_from_id', array('userid' => pnUserGetVar('uid')));
        // create unix timestamp
        $post['post_unixtime']   = time();
        $post['posted_unixtime'] = $post['post_unixtime'];

        $post['post_title'] = $title;
        $post['post_textdisplay'] = phpbb_br2nl($message);
        if ($attach_signature == 1) {
            $post['post_textdisplay'] .= '[addsig]';
            $post['post_textdisplay'] = Dizkus_replacesignature($post['post_textdisplay'], $post['poster_data']['_SIGNATURE']);
        }
        // call hooks for $message_display ($message remains untouched for the textarea)
        list($post['post_textdisplay']) = pnModCallHooks('item', 'transform', $post['post_id'], array($post['post_textdisplay']));
        $post['post_textdisplay'] =dzkVarPrepHTMLDisplay($post['post_textdisplay']);

        $post['post_text'] = $post['post_textdisplay'];
    }

    $pnr = pnRender::getInstance('Dizkus', false, null, true);
    $pnr->assign('topic', $topic);
    $pnr->assign('post', $post);
    $pnr->assign('preview', $preview);

    //---- begin of MediaAttach integration ----
    if (pnModAvailable('MediaAttach') && pnModIsHooked('MediaAttach', 'Dizkus')) {
        dzk_jsonizeoutput(array('data'    => $pnr->fetch('dizkus_user_singlepost.html'),
                                'post_id' => $post['post_id'],
                                'uploadauthid' => pnSecGenAuthKey('MediaAttach')),
                          true);

    } else {
        dzk_jsonizeoutput(array('data'    => $pnr->fetch('dizkus_user_singlepost.html'),
                                'post_id' => $post['post_id']),
                          true);
    }
    //---- end of MediaAttach integration ----
}

/**
 * preparequote
 *
 */
function Dizkus_ajax_preparequote()
{
    $dom = ZLanguage::getModuleDomain('Dizkus');

    if (dzk_available(false) == false) {
       dzk_ajaxerror(strip_tags(pnModGetVar('Dizkus', 'forum_disabled_info')));
    }

    $post_id = FormUtil::getPassedValue('post');

    SessionUtil::setVar('pn_ajax_call', 'ajax');

    if (!empty($post_id)) {
        $post = pnModAPIFunc('Dizkus', 'user', 'preparereply',
                             array('post_id'     => $post_id,
                                   'quote'       => true,
                                   'reply_start' => true));
        dzk_jsonizeoutput($post, false);
    }
    dzk_ajaxerror('internal error: no post id in Dizkus_ajax_preparequote()');
}

/**
 * readpost
 *
 */
function Dizkus_ajax_readpost()
{
    $dom = ZLanguage::getModuleDomain('Dizkus');

    if (dzk_available(false) == false) {
       dzk_ajaxerror(strip_tags(pnModGetVar('Dizkus', 'forum_disabled_info')));
    }

    $post_id = FormUtil::getPassedValue('post');

    SessionUtil::setVar('pn_ajax_call', 'ajax');

    if (!empty($post_id)) {
        $post = pnModAPIFunc('Dizkus', 'user', 'readpost',
                             array('post_id'     => $post_id));
        if ($post['poster_data']['edit'] == true) {
            dzk_jsonizeoutput($post, false);
        } else {
            dzk_ajaxerror(__('No permission for this action', $dom));
        }
    }
    dzk_ajaxerror('internal error: no post id in Dizkus_ajax_readpost()');
}

/**
 * editpost
 *
 */
function Dizkus_ajax_editpost()
{
    $dom = ZLanguage::getModuleDomain('Dizkus');

    if (dzk_available(false) == false) {
       dzk_ajaxerror(strip_tags(pnModGetVar('Dizkus', 'forum_disabled_info')));
    }

    $post_id = FormUtil::getPassedValue('post');

    SessionUtil::setVar('pn_ajax_call', 'ajax');

    if (!empty($post_id)) {
        $post = pnModAPIFunc('Dizkus', 'user', 'readpost',
                             array('post_id'     => $post_id));
        if ($post['poster_data']['edit'] == true) {
            $pnr = pnRender::getInstance('Dizkus', false, null, true);
            $pnr->assign('post', $post);
            // simplify our live
            $pnr->assign('postingtextareaid', 'postingtext_' . $post['post_id'] . '_edit');

            SessionUtil::delVar('pn_ajax_call');

            return array('data'    => $pnr->fetch('dizkus_ajax_editpost.html'),
                         'post_id' => $post['post_id']);
        } else {
            dzk_ajaxerror(__('No permission for this action', $dom));
        }
    }
    dzk_ajaxerror('internal error: no post id in Dizkus_ajax_readrawtext()');
}

/**
 * updatepost
 *
 */
function Dizkus_ajax_updatepost()
{
    $dom = ZLanguage::getModuleDomain('Dizkus');

    if (dzk_available(false) == false) {
       dzk_ajaxerror(strip_tags(pnModGetVar('Dizkus', 'forum_disabled_info')));
    }

    $post_id = FormUtil::getPassedValue('post', '');
    $subject = FormUtil::getPassedValue('subject', '');
    $message = FormUtil::getPassedValue('message', '');
    $delete  = FormUtil::getPassedValue('delete');
    $attach_signature = FormUtil::getPassedValue('attach_signature');

    SessionUtil::setVar('pn_ajax_call', 'ajax');

    if (!empty($post_id)) {
        if (!SecurityUtil::confirmAuthKey()) {
            dzk_ajaxerror(__('Invalid \'authkey\':  this probably means that you pressed the \'Back\' button, or that the page \'authkey\' expired. Please refresh the page and try again.', $dom));
        }
 
        $message = dzkstriptags(DataUtil::convertFromUTF8($message));
        // check for maximum message size
        if ((strlen($message) + 8/*strlen('[addsig]')*/) > 65535) {
            dzk_ajaxerror(__('Illegal message size, max. 65535 chars', $dom));
        }

        pnModAPIFunc('Dizkus', 'user', 'updatepost',
                     array('post_id'          => $post_id,
                           'subject'          => DataUtil::convertFromUTF8($subject),
                           'message'          => $message,
                           'delete'           => $delete,
                           'attach_signature' => ($attach_signature==1)));

        if ($delete <> '1') {
            $post = pnModAPIFunc('Dizkus', 'user', 'readpost',
                                 array('post_id'     => $post_id));
            $post['action'] = 'updated';
        } else {
            $post = array('action'  => 'deleted',
                          'post_id' => $post_id);
        }

        SessionUtil::delVar('pn_ajax_call');

        return $post;
    }
    dzk_ajaxerror('internal error: no post id in Dizkus_ajax_updatepost()');
}

/**
 * lockunlocktopic
 *
 */
function Dizkus_ajax_lockunlocktopic()
{
    $dom = ZLanguage::getModuleDomain('Dizkus');

    if (dzk_available(false) == false) {
       dzk_ajaxerror(strip_tags(pnModGetVar('Dizkus', 'forum_disabled_info')));
    }

    $topic_id = FormUtil::getPassedValue('topic', '');
    $mode     = FormUtil::getPassedValue('mode', '');

    SessionUtil::setVar('pn_ajax_call', 'ajax');

    if (!SecurityUtil::confirmAuthKey()) {
       //dzk_ajaxerror(__('Invalid \'authkey\':  this probably means that you pressed the \'Back\' button, or that the page \'authkey\' expired. Please refresh the page and try again.', $dom));
    }

    if (empty($topic_id)) {
        dzk_ajaxerror('internal error: no topic id in Dizkus_ajax_lockunlocktopic()');
    }
    if (empty($mode) || (($mode <> 'lock') && ($mode <> 'unlock')) ) {
        dzk_ajaxerror('internal error: no or illegal mode (' . DataUtil::formatForDisplay($mode) . ') parameter in Dizkus_ajax_lockunlocktopic()');
    }

    list($forum_id, $cat_id) = pnModAPIFunc('Dizkus', 'user', 'get_forumid_and_categoryid_from_topicid',
                                            array('topic_id' => $topic_id));

    if (!allowedtomoderatecategoryandforum($cat_id, $forum_id)) {
        return dzk_ajaxerror(__('You have no permission to moderate this category or forum', $dom));
    }

    pnModAPIFunc('Dizkus', 'user', 'lockunlocktopic',
                 array('topic_id' => $topic_id,
                       'mode'     => $mode));

    $newmode = ($mode=='lock') ? 'locked' : 'unlocked';

    dzk_jsonizeoutput($newmode);
}

/**
 * stickyunstickytopic
 *
 */
function Dizkus_ajax_stickyunstickytopic()
{
    $dom = ZLanguage::getModuleDomain('Dizkus');

    if (dzk_available(false) == false) {
       dzk_ajaxerror(strip_tags(pnModGetVar('Dizkus', 'forum_disabled_info')));
    }

    $topic_id = FormUtil::getPassedValue('topic', '');
    $mode     = FormUtil::getPassedValue('mode', '');

    SessionUtil::setVar('pn_ajax_call', 'ajax');

    if (!SecurityUtil::confirmAuthKey()) {
       //dzk_ajaxerror(__('Invalid \'authkey\':  this probably means that you pressed the \'Back\' button, or that the page \'authkey\' expired. Please refresh the page and try again.', $dom));
    }

    if (empty($topic_id)) {
        dzk_ajaxerror('internal error: no topic id in Dizkus_ajax_stickyunstickytopic()');
    }
    if (empty($mode) || (($mode <> 'sticky') && ($mode <> 'unsticky')) ) {
        dzk_ajaxerror('internal error: no or illegal mode (' . DataUtil::formatForDisplay($mode) . ') parameter in Dizkus_ajax_stickyunstickytopic()');
    }

    list($forum_id, $cat_id) = pnModAPIFunc('Dizkus', 'user', 'get_forumid_and_categoryid_from_topicid',
                                            array('topic_id' => $topic_id));

    if (!allowedtomoderatecategoryandforum($cat_id, $forum_id)) {
        return dzk_ajaxerror(__('You have no permission to moderate this category or forum', $dom));
    }

    pnModAPIFunc('Dizkus', 'user', 'stickyunstickytopic',
                 array('topic_id' => $topic_id,
                       'mode'     => $mode));

    dzk_jsonizeoutput($mode);
}

/**
 * subscribeunsubscribetopic
 *
 */
function Dizkus_ajax_subscribeunsubscribetopic()
{
    $dom = ZLanguage::getModuleDomain('Dizkus');

    if (dzk_available(false) == false) {
       dzk_ajaxerror(strip_tags(pnModGetVar('Dizkus', 'forum_disabled_info')));
    }

    $topic_id = FormUtil::getPassedValue('topic', '');
    $mode     = FormUtil::getPassedValue('mode', '');

    SessionUtil::setVar('pn_ajax_call', 'ajax');
/*
    if (!SecurityUtil::confirmAuthKey()) {
        dzk_ajaxerror(__('Invalid \'authkey\':  this probably means that you pressed the \'Back\' button, or that the page \'authkey\' expired. Please refresh the page and try again.', $dom));
    }
*/
    if (empty($topic_id)) {
        dzk_ajaxerror('internal error: no topic id in Dizkus_ajax_subscribeunsubscribetopic()');
    }

    list($forum_id, $cat_id) = pnModAPIFunc('Dizkus', 'user', 'get_forumid_and_categoryid_from_topicid',
                                            array('topic_id' => $topic_id));

    if (!allowedtoreadcategoryandforum($cat_id, $forum_id)) {
        return dzk_ajaxerror(__('You have no permission to read the content of this category or forum', $dom));
    }

    switch($mode) {
        case 'subscribe':
            pnModAPIFunc('Dizkus', 'user', 'subscribe_topic',
                         array('topic_id' => $topic_id,
                               'silent'   => true));
            $newmode = 'subscribed';
            break;
        case 'unsubscribe':
            pnModAPIFunc('Dizkus', 'user', 'unsubscribe_topic',
                         array('topic_id' => $topic_id,
                               'silent'   => true));
            $newmode = 'unsubscribed';
            break;
        default:
            dzk_ajaxerror('internal error: no or illegal mode (' . DataUtil::formatForDisplay($mode) . ') parameter in Dizkus_ajax_subscribeunsubscribetopic()');
    }

    dzk_jsonizeoutput($newmode);
}

/**
 * subscribeunsubscribeforum
 *
 */
function Dizkus_ajax_subscribeunsubscribeforum()
{
    $dom = ZLanguage::getModuleDomain('Dizkus');

    if (dzk_available(false) == false) {
       dzk_ajaxerror(strip_tags(pnModGetVar('Dizkus', 'forum_disabled_info')));
    }

    $forum_id = FormUtil::getPassedValue('forum', '');
    $mode     = FormUtil::getPassedValue('mode', '');

    SessionUtil::setVar('pn_ajax_call', 'ajax');
/*
    if (!SecurityUtil::confirmAuthKey()) {
        dzk_ajaxerror(__('Invalid \'authkey\':  this probably means that you pressed the \'Back\' button, or that the page \'authkey\' expired. Please refresh the page and try again.', $dom));
    }
*/
    if (empty($forum_id)) {
        dzk_ajaxerror('internal error: no forum id in Dizkus_ajax_subscribeunsubscribeforum()');
    }

    $cat_id = pnModAPIFunc('Dizkus', 'user', 'get_forum_category',
                           array('forum_id' => $forum_id));

    if (!allowedtoreadcategoryandforum($cat_id, $forum_id)) {
        return dzk_ajaxerror(__('You have no permission to read the content of this category or forum', $dom));
    }

    switch($mode) {
        case 'subscribe':
            pnModAPIFunc('Dizkus', 'user', 'subscribe_forum',
                         array('forum_id' => $forum_id,
                               'silent'   => true));
            $newmode = 'subscribed';
            break;
        case 'unsubscribe':
            pnModAPIFunc('Dizkus', 'user', 'unsubscribe_forum',
                         array('forum_id' => $forum_id,
                               'silent'   => true));
            $newmode = 'unsubscribed';
            break;
        default:
            dzk_ajaxerror('internal error: no or illegal mode (' . DataUtil::formatForDisplay($mode) . ') parameter in Dizkus_ajax_subscribeunsubscribeforum()');
    }

    dzk_jsonizeoutput(array('newmode' => $newmode,
                            'forum_id' => $forum_id));
}

/**
 * addremovefavorite
 *
 */
function Dizkus_ajax_addremovefavorite()
{
    $dom = ZLanguage::getModuleDomain('Dizkus');

    if (dzk_available(false) == false) {
       dzk_ajaxerror(strip_tags(pnModGetVar('Dizkus', 'forum_disabled_info')));
    }

    if (pnModGetVar('Dizkus', 'favorites_enabled') == 'no') {
        dzk_ajaxerror(__('Favorites have been disabled', $dom));
    }

    $forum_id = FormUtil::getPassedValue('forum', '');
    $mode     = FormUtil::getPassedValue('mode', '');

    if (empty($forum_id)) {
        dzk_ajaxerror('internal error: no forum id in Dizkus_ajax_addremovefavorite()');
    }
/*
    if (!SecurityUtil::confirmAuthKey()) {
        dzk_ajaxerror(__('Invalid \'authkey\':  this probably means that you pressed the \'Back\' button, or that the page \'authkey\' expired. Please refresh the page and try again.', $dom));
    }
*/
    SessionUtil::setVar('pn_ajax_call', 'ajax');

    $cat_id = pnModAPIFunc('Dizkus', 'user', 'get_forum_category',
                           array('forum_id' => $forum_id));

    if (!allowedtoreadcategoryandforum($cat_id, $forum_id)) {
        return dzk_ajaxerror(__('You have no permission to read the content of this category or forum', $dom));
    }

    switch($mode) {
        case 'add':
            pnModAPIFunc('Dizkus', 'user', 'add_favorite_forum',
                         array('forum_id' => $forum_id ));
            $newmode = 'added';
            break;
        case 'remove':
            pnModAPIFunc('Dizkus', 'user', 'remove_favorite_forum',
                         array('forum_id' => $forum_id ));
            $newmode = 'removed';
            break;
        default:
            dzk_ajaxerror('internal error: no or illegal mode (' . DataUtil::formatForDisplay($mode) . ') parameter in Dizkus_ajax_addremovefavorite()');
    }

    dzk_jsonizeoutput(array('newmode' => $newmode,
                            'forum_id' => $forum_id));
}

/**
 * edittopicsubject
 *
 */
function Dizkus_ajax_edittopicsubject()
{
    $dom = ZLanguage::getModuleDomain('Dizkus');

    if (dzk_available(false) == false) {
       dzk_ajaxerror(strip_tags(pnModGetVar('Dizkus', 'forum_disabled_info')));
    }

    $topic_id = FormUtil::getPassedValue('topic', '');

    SessionUtil::setVar('pn_ajax_call', 'ajax');

    if (!empty($topic_id)) {
        $topic = pnModAPIFunc('Dizkus', 'user', 'readtopic',
                             array('topic_id' => $topic_id,
                                   'count'    => false,
                                   'complete' => false));

        if ($topic['access_topicsubjectedit'] == true) {
            $pnr = pnRender::getInstance('Dizkus', false, null, true);
            $pnr->assign('topic', $topic);

            SessionUtil::delVar('pn_ajax_call');

            return array('data'     => $pnr->fetch('dizkus_ajax_edittopicsubject.html'),
                         'topic_id' => $topic_id);
        } else {
            dzk_ajaxerror(__('No permission for this action', $dom));
        }
    }
    dzk_ajaxerror('internal error: no topic id in Dizkus_ajax_readtopic()');
}

/**
 * updatetopicsubject
 *
 */
function Dizkus_ajax_updatetopicsubject()
{
    $dom = ZLanguage::getModuleDomain('Dizkus');

    if (dzk_available(false) == false) {
       dzk_ajaxerror(strip_tags(pnModGetVar('Dizkus', 'forum_disabled_info')));
    }

    $topic_id = FormUtil::getPassedValue('topic', '');
    $subject  = FormUtil::getPassedValue('subject', '');

    SessionUtil::setVar('pn_ajax_call', 'ajax');

    if (!empty($topic_id)) {
        if (!SecurityUtil::confirmAuthKey()) {
           dzk_ajaxerror(__('Invalid \'authkey\':  this probably means that you pressed the \'Back\' button, or that the page \'authkey\' expired. Please refresh the page and try again.', $dom));
        }

        list($forum_id, $cat_id) = pnModAPIFunc('Dizkus', 'user', 'get_forumid_and_categoryid_from_topicid', array('topic_id' => $topic_id));
        if (!allowedtomoderatecategoryandforum($cat_id, $forum_id)) {
            dzk_ajaxerror(__('You have no permission to moderate this category or forum', $dom));
        }

        $subject = trim(DataUtil::convertFromUTF8($subject));
        if (empty($subject)) {
            dzk_ajaxerror(__('no subject', $dom));
        }

        $topic['topic_id']    = $topic_id;
        $topic['topic_title'] = DataUtil::formatForStore($subject);
        $res = DBUtil::updateObject($topic, 'dizkus_topics', '', 'topic_id');

        // Let any hooks know that we have updated an item.
        pnModCallHooks('item', 'update', $topic_id, array('module'   => 'Dizkus',
                                                          'topic_id' => $topic_id));

        SessionUtil::delVar('pn_ajax_call');

        return array('topic_title' => DataUtil::formatForDisplay($subject),
                     'topic_id'    => $topic_id);
    }
    dzk_ajaxerror('internal error: no topic id in Dizkus_ajax_updatetopicsubject()');
}

/**
 * changesortorder
 *
 */
function Dizkus_ajax_changesortorder()
{
    $dom = ZLanguage::getModuleDomain('Dizkus');

    if (dzk_available(false) == false) {
       dzk_ajaxerror(strip_tags(pnModGetVar('Dizkus', 'forum_disabled_info')));
    }

    SessionUtil::setVar('pn_ajax_call', 'ajax');

    if (!pnUserLoggedIn()) {
       dzk_ajaxerror(__('This functionality is for registered users only', $dom));
    }

    if (!SecurityUtil::confirmAuthKey()) {
       dzk_ajaxerror(__('Invalid \'authkey\':  this probably means that you pressed the \'Back\' button, or that the page \'authkey\' expired. Please refresh the page and try again.', $dom));
    }

    pnModAPIFunc('Dizkus', 'user', 'change_user_post_order');
    $newmode = strtolower(pnModAPIFunc('Dizkus','user','get_user_post_order'));
    dzk_jsonizeoutput($newmode, true, true);
}

/**
 * newtopic
 *
 */
function Dizkus_ajax_newtopic()
{
/*
    if (!SecurityUtil::confirmAuthKey()) {
       dzk_ajaxerror(__('Invalid \'authkey\':  this probably means that you pressed the \'Back\' button, or that the page \'authkey\' expired. Please refresh the page and try again.', $dom));
    }
*/
    $dom = ZLanguage::getModuleDomain('Dizkus');

    if (dzk_available(false) == false) {
       dzk_ajaxerror(strip_tags(pnModGetVar('Dizkus', 'forum_disabled_info')));
    }

    SessionUtil::setVar('pn_ajax_call', 'ajax');

    $forum_id         = FormUtil::getPassedValue('forum');
    $message          = FormUtil::getPassedValue('message', '');
    $subject          = FormUtil::getPassedValue('subject', '');
    $attach_signature = FormUtil::getPassedValue('attach_signature');
    $subscribe_topic  = FormUtil::getPassedValue('subscribe_topic');
    $preview          = (int)FormUtil::getPassedValue('preview', 0);

    $cat_id = pnModAPIFunc('Dizkus', 'user', 'get_forum_category',
                           array('forum_id' => $forum_id));

    if (!allowedtowritetocategoryandforum($cat_id, $forum_id)) {
        return dzk_ajaxerror(__('You have no permission to write into this category or forum', $dom));
    }

    $preview          = ($preview == 1) ? true : false;
    //$attach_signature = ($attach_signature=='1') ? true : false;
    //$subscribe_topic  = ($subscribe_topic=='1') ? true : false;

    $message = dzkstriptags(DataUtil::convertFromUTF8($message));
    // check for maximum message size
    if ((strlen($message) + 8/*strlen('[addsig]')*/) > 65535) {
        dzk_ajaxerror(__('Illegal message size, max. 65535 chars', $dom), true);
    }
    if (strlen($message) == 0) {
        dzk_ajaxerror(__('You must type a message to post. You cannot post an empty message. Please go back and try again.', $dom), true);
    }

    $subject = DataUtil::convertFromUTF8($subject);
    if (strlen($subject) == 0) {
        dzk_ajaxerror(__('no subject', $dom), true);
    }

    $pnr = pnRender::getInstance('Dizkus', false, null, true);

    if ($preview == false) {
        if (!SecurityUtil::confirmAuthKey()) {
           dzk_ajaxerror(__('Invalid \'authkey\':  this probably means that you pressed the \'Back\' button, or that the page \'authkey\' expired. Please refresh the page and try again.', $dom));
        }

        // store new topic
        $topic_id = pnModAPIFunc('Dizkus', 'user', 'storenewtopic',
                                 array('forum_id'         => $forum_id,
                                       'subject'          => $subject,
                                       'message'          => $message,
                                       'attach_signature' => $attach_signature,
                                       'subscribe_topic'  => $subscribe_topic));

        $topic = pnModAPIFunc('Dizkus', 'user', 'readtopic',
                              array('topic_id' => $topic_id, 
                                    'count'    => false));

        if (pnModGetVar('Dizkus', 'newtopicconfirmation') == 'yes') {
            $pnr->assign('topic', $topic);
            $confirmation = $pnr->fetch('dizkus_ajax_newtopicconfirmation.html');
        } else {
            $confirmation = false;
        }

        // --- MediaAttach check ---
        if (pnModAvailable('MediaAttach') && pnModIsHooked('MediaAttach', 'Dizkus')) {
            dzk_jsonizeoutput(array('topic'        => $topic,
                                    'confirmation' => $confirmation,
                                    'redirect'     => pnModURL('Dizkus', 'user', 'viewtopic',
                                                               array('topic' => $topic_id),
                                                               null, null, true),
                                    'uploadredirect' => urlencode(pnModURL('Dizkus', 'user', 'viewtopic',
                                                                           array('topic' => $topic_id))),
                                    'uploadobjectid' => $topic_id,
                                    'uploadauthid' => pnSecGenAuthKey('MediaAttach')
                                   ),
                              true);

        } else {
            dzk_jsonizeoutput(array('topic'        => $topic,
                                    'confirmation' => $confirmation,
                                    'redirect'     => pnModURL('Dizkus', 'user', 'viewtopic',
                                                               array('topic' => $topic_id),
                                                               null, null, true)
                                   ),
                              true);
        }
    }

    // preview == true, create fake topic
    $newtopic['cat_id']     = $cat_id;
    $newtopic['forum_id']   = $forum_id;
//    $newtopic['forum_name'] = DataUtil::formatForDisplay($myrow['forum_name']);
//    $newtopic['cat_title']  = DataUtil::formatForDisplay($myrow['cat_title']);

    $newtopic['topic_unixtime'] = time();

    // need at least "comment" to add newtopic
    if (!allowedtowritetocategoryandforum($newtopic['cat_id'], $newtopic['forum_id'])) {
        // user is not allowed to post
        return showforumerror(__('You have no permission to write into this category or forum', $dom), __FILE__, __LINE__);
    }

    $newtopic['poster_data'] = Dizkus_userapi_get_userdata_from_id(array('userid' => pnUserGetVar('uid')));

    $newtopic['subject'] = $subject;
    $newtopic['message'] = $message;
    $newtopic['message_display'] = $message; // phpbb_br2nl($message);

    if ($attach_signature == 1) {
        $newtopic['message_display'] .= '[addsig]';
        $newtopic['message_display'] = Dizkus_replacesignature($newtopic['message_display'], $newtopic['poster_data']['_SIGNATURE']);
    }

    list($newtopic['message_display']) = pnModCallHooks('item', 'transform', '', array($newtopic['message_display']));
    $newtopic['message_display']       = dzkVarPrepHTMLDisplay($newtopic['message_display']);

    if (pnUserLoggedIn()) {
        // If it's the topic start
        if (empty($subject) && empty($message)) {
            $newtopic['attach_signature'] = 1;
            $newtopic['subscribe_topic']  = (pnModGetVar('Dizkus', 'autosubscribe') == 'yes') ? 1 : 0;
        } else {
            $newtopic['attach_signature'] = $attach_signature;
            $newtopic['subscribe_topic']  = $subscribe_topic;
        }
    } else {
        $newtopic['attach_signature'] = 0;
        $newtopic['subscribe_topic']  = 0;
    }

    $pnr->assign('newtopic', $newtopic);

    SessionUtil::delVar('pn_ajax_call');

    return array('data'     => $pnr->fetch('dizkus_user_newtopicpreview.html'),
                 'newtopic' => $newtopic);
}

/**
 * forumusers
 * update the "users online" section in the footer
 * original version by gf
 *
 */
function Dizkus_ajax_forumusers()
{
    $dom = ZLanguage::getModuleDomain('Dizkus');

    if (dzk_available(false) == false) {
       dzk_ajaxerror(strip_tags(pnModGetVar('Dizkus', 'forum_disabled_info')));
    }

    $pnr = pnRender::getInstance('Dizkus', false);

    if (pnConfigGetVar('shorturls')) {
        Loader::includeOnce('system/Theme/plugins/outputfilter.shorturls.php');
        $pnr->register_outputfilter('smarty_outputfilter_shorturls');
    }

    $pnr->display('dizkus_ajax_forumusers.html');
    pnShutDown();
}

/**
 * newposts
 * update the "new posts" block
 * original version by gf
 *
 */
function Dizkus_ajax_newposts()
{
    $dom = ZLanguage::getModuleDomain('Dizkus');

    if (!is_bool($disabled = dzk_available())) {
        echo $disabled;
        pnShutDown();
    }

    $pnr = pnRender::getInstance('Dizkus', false);

    if (pnConfigGetVar('shorturls')) {
        Loader::includeOnce('system/Theme/plugins/outputfilter.shorturls.php');
        $pnr->register_outputfilter('smarty_outputfilter_shorturls');
    }

    $out = $pnr->fetch('dizkus_ajax_newposts.html');
    echo DataUtil::convertToUTF8($out);
    pnShutDown();
}
