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

/**
 * readlastposts
 * reads the last $maxposts postings of forum $forum_id and assign them in a
 * variable lastposts and the number of them in lastpostcount
 *
 * @params maxposts (int) number of posts to read, default = 5
 * @params forum_id (int) forum_id, if not set, all forums
 * @params user_id  (int) -1 = last postings of current user, otherwise its treated as an user_id
 * @params canread (bool) if set, only the forums that we have read access to [** flag is no longer supported, this is the default settings for now **]
 * @params favorites (bool) if set, only the favorite forums
 * @params show_m2f (bool) if set show postings from mail2forum forums
 * @params show_rss (bool) if set show postings from rss2forum forums
 *
 */
function smarty_function_readlastposts($params, &$smarty)
{
    // TODO deprecate the use of extract
    extract($params);
    unset($params);

    $maxposts = (isset($maxposts) && is_numeric($maxposts) && $maxposts > 0) ? $maxposts : 5;
    // we limit maxposts to 100... just to be safe :-)
    $maxposts = ($maxposts>100) ? 100 : $maxposts;

    $loggedIn = pnUserLoggedIn();
    $uid = ($loggedIn == true) ? pnUserGetVar('uid') : 1;

    // get number of posts in db
    $numposts = pnModAPIFunc('Dizkus', 'user', 'boardstats', array('type' => 'all'));
    if ($numposts==0) {
        $smarty->assign('lastpostcount', 0);
        $smarty->assign('lastposts', array());
        return;
    }

    Loader::includeOnce('modules/Dizkus/common.php');
    // get some enviroment
    $pntable = pnDBGetTables();

    $whereforum = '';
    if (!empty($forum_id) && is_numeric($forum_id)) {
        // get the category id and check permissions
        $cat_id = pnModAPIFunc('Dizkus', 'user', 'get_forum_category',
                               array('forum_id' => $forum_id));
        if (!allowedtoreadcategoryandforum($cat_id, $forum_id)) {
            $smarty->assign('lastpostcount', 0);
            $smarty->assign('lastposts', array());
            return;
        }
        $whereforum = 't.forum_id = ' . DataUtil::formatForStore($forum_id) . ' AND ';
    } else if (!isset($favorites)) {
        // no special forum_id set, get all forums the user is allowed to read
        // and build the where part of the sql statement
        $userforums = pnModAPIFunc('Dizkus', 'user', 'readuserforums');
        if (!is_array($userforums) || count($userforums)==0) {
            // error or user is not allowed to read any forum at all
            $smarty->assign('lastpostcount', 0);
            $smarty->assign('lastposts', array());
            return;
        }
        
        foreach($userforums as $userforum) {
            if (strlen($whereforum)>0) {
                $whereforum .= ', ';
            }
            $whereforum .= $userforum['forum_id'];
        }
        $whereforum = 't.forum_id IN (' . DataUtil::formatForStore($whereforum) . ') AND';
       }

    $wherefavorites = '';
    // we only want to do this if $favorites is set and $whereforum is empty
    // and the user is logged in.
    // (Anonymous doesn't have favorites)
    if (isset($favorites) && $favorites && empty($whereforum) && $loggedIn) {
        // get the favorites
        $sql = 'SELECT fav.forum_id,
                       f.cat_id
                FROM ' . $pntable['dizkus_forum_favorites'] . ' fav
                LEFT JOIN ' . $pntable['dizkus_forums'] . ' f
                ON f.forum_id = fav.forum_id
                WHERE fav.user_id = ' . DataUtil::formatForStore($uid);
        
        $res       = DBUtil::executeSQL($sql);
        $colarray  = array('forum_id', 'cat_id');
        $result    = DBUtil::marshallObjects($res, $colarray);
        if (is_array($result) && !empty($result)) {
            foreach ($result as $resline) {
                // append 'OR' if $wherefavorites is not empty
                if (!empty($wherefavorites)) {
                    $wherefavorites .= ' OR ';
                }
                if (allowedtoreadcategoryandforum($resline['cat_id'], $resline['forum_id'])) {
                    $wherefavorites .= 'f.forum_id=' .  (int)DataUtil::formatForStore($resline['forum_id']); // . ' OR ';
                }
            }
        }

        if (!empty($wherefavorites)) {
            $wherefavorites = '(' . $wherefavorites . ') AND';
        }
    }

    $wherespecial = ' (f.forum_pop3_active = 0';
    // if show_m2f is set we show contents of m2f forums where.
    // forum_pop3_active is set to 1
    if (isset($show_m2f) && $show_m2f==true) {
        $wherespecial .= ' OR f.forum_pop3_active = 1';
    }
    // if show_rss is set we show contents of rss2f forums where.
    // forum_pop3_active is set to 2
    if (isset($show_rss) && $show_rss==true) {
        $wherespecial .= ' OR f.forum_pop3_active = 2';
    }

    $wherespecial .= ') AND ';

    //check how much we have to read
    $postmax = ($numposts < $maxposts) ? $numposts : $maxposts;

    // user_id set?
    $whereuser = "";
    if (!empty($user_id)) {
        if ($user_id==-1 && $loggedIn) {
            $whereuser = 'p.poster_id = ' . DataUtil::formatForStore($uid) . ' AND ';
        } else {
            $whereuser = 'p.poster_id = ' . DataUtil::formatForStore($user_id) . ' AND ';
        }
    }
       
    $sql = 'SELECT t.topic_id,
                   t.topic_title,
                   t.topic_poster,
                   t.topic_replies,
                   t.topic_time,
                   t.topic_last_post_id,
                   t.sticky,
                   t.topic_status,
                   t.topic_views,
                   f.forum_id,
                   f.forum_name,
                   c.cat_title,
                   c.cat_id,
                   p.poster_id,
                   p.post_id,
                   p.post_text
        FROM ' . $pntable['dizkus_topics']     . ' as t,
             ' . $pntable['dizkus_forums']     . ' as f,
             ' . $pntable['dizkus_posts']      . ' as p,
             ' . $pntable['dizkus_categories'] . ' as c
        WHERE ' . $whereforum .'
              ' . $whereuser . '
              ' . $wherefavorites . '
              ' . $wherespecial . '
              t.forum_id = f.forum_id AND
              t.topic_last_post_id = p.post_id AND
              f.cat_id = c.cat_id
        ORDER by t.topic_time DESC';

    $lastposts = array();

    // if the user wants to see the last x postings we read 5 * x because
    // we might get to forums he is not allowed to see
    // we do this until we got the requested number of postings
    $res = DBUtil::executeSQL($sql);
    $colarray = array('topic_id', 'topic_title', 'topic_poster', 'topic_replies', 'topic_time', 'topic_last_post_id', 'sticky', 'topic_status',
                      'topic_views', 'forum_id', 'forum_name', 'cat_title', 'cat_id', 'poster_id', 'post_id', 'post_text');
    $result    = DBUtil::marshallObjects($res, $colarray);

    if (is_array($result) && !empty($result)) {
        $post_sort_order = pnModAPIFunc('Dizkus', 'user', 'get_user_post_order');
        $posts_per_page  = pnModGetVar('Dizkus', 'posts_per_page');
        foreach ($result as $lastpost) {
            $lastpost['topic_title'] = DataUtil::formatforDisplay($lastpost['topic_title']);
            $lastpost['forum_name']  = DataUtil::formatforDisplay($lastpost['forum_name']);
            $lastpost['cat_title']   = DataUtil::formatforDisplay($lastpost['cat_title']);

            // backwards compatibility... :puke:
            $lastpost['title_tag'] = $lastpost['topic_title'];

            if ($post_sort_order == "ASC") {
                $start = ((ceil(($lastpost['topic_replies'] + 1)  / $posts_per_page) - 1) * $posts_per_page);
            } else {
                // latest topic is on top anyway...
                $start = 0;
            }
            $lastpost['start'] = $start;
            if ($lastpost['poster_id'] != 1) {
                $user_name = pnUserGetVar('uname', $lastpost['poster_id']);
                if ($user_name == "") {
                    // user deleted from the db?
                    $user_name = pnModGetVar('Users', 'anonymous');
                }
            } else {
                $user_name = pnModGetVar('Users', 'anonymous');
            }
            $lastpost['poster_name'] = DataUtil::formatForDisplay($user_name);

            $lastpost['post_text'] = Dizkus_replacesignature($lastpost['post_text'], '');
            // call hooks for $message
            list($lastpost['post_text']) = pnModCallHooks('item', 'transform', '', array($lastpost['post_text']));
            $lastpost['post_text'] = DataUtil::formatForDisplay(nl2br($lastpost['post_text']));

            $posted_unixtime= strtotime ($lastpost['topic_time']);
            $posted_ml = DateUtil::formatDatetime($posted_unixtime, 'datetimebrief');
            $lastpost['posted_time'] =$posted_ml;
            $lastpost['posted_unixtime'] = $posted_unixtime;

            // we now create the url to the last post in the thread. This might be
            // on site 1, 2 or what ever in the thread, depending on topic_replies
            // count and the posts_per_page setting
            $lastpost['last_post_url'] = DataUtil::formatForDisplay(pnModURL('Dizkus', 'user', 'viewtopic',
                                                             array('topic' => $lastpost['topic_id'],
                                                                   'start' => $lastpost['start'])));
            $lastpost['last_post_url_anchor'] = $lastpost['last_post_url'] . "#pid" . $lastpost['topic_last_post_id'];

            array_push($lastposts, $lastpost);
        }
    }

    $smarty->assign('lastpostcount', count($lastposts));
    $smarty->assign('lastposts', $lastposts);
    return;
}
