<?php
// $Id$
// ----------------------------------------------------------------------
// PostNuke Content Management System
// Copyright (C) 2002 by the PostNuke Development Team.
// http://www.postnuke.com/
// ----------------------------------------------------------------------
// Based on:
// PHP-NUKE Web Portal System - http://phpnuke.org/
// Thatware - http://thatware.org/
// ----------------------------------------------------------------------
// LICENSE
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License (GPL)
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// To read the license please visit http://www.gnu.org/copyleft/gpl.html
// ----------------------------------------------------------------------

/**
 * pnRender plugin
 *
 * This file is a plugin for pnRender, the PostNuke implementation of Smarty
 *
 * @package      Xanthia_Templating_Environment
 * @subpackage   pnRender
 * @version      $Id$
 * @author       The PostNuke development team
 * @link         http://www.postnuke.com  The PostNuke Home Page
 * @copyright    Copyright (C) 2002 by the PostNuke Development Team
 * @license      http://www.gnu.org/copyleft/gpl.html GNU General Public License
 */


/**
 * Smarty function to read similar topics compared to topic subject
 *
 * This function returns an array of x similar topics assign to $similartopics,
 * same format as result from original search
 *
 * Available parameters:
 *   - search:  the search string
 *   - limit: the number of topics to return, default 5
 *
 * Example
 *   <!--[similartopics search=$post.topic_subject limit=3]-->
 *
 *
 * @author       Frank Schummertz
 * @since        03/25/2006
 * @param        array       $params      All attributes passed to this function from the template
 * @param        object      &$smarty     Reference to the Smarty object
 * @return       array
 */

include_once 'modules/pnForum/common.php';

function smarty_function_similartopics($params, &$smarty)
{
    extract($params);
    unset($params);

    if(!isset($search) || empty($search)) {
        $smarty->trigger_error('similartopics: attribute search required');
        return false;
    }
            
    $limit = (isset($limit)) ? $limit : 5;

    $vars['searchfor'] = $search;
    $vars['bool']      = 'AND';
    $vars['forums'][0] = -1;
    $vars['author']    = '';
    $vars['limit']     = $limit;
    $vars['startnum']  = 0;

    if(pnModGetVar('pnForum', 'fulltextindex')==1) {
        $funcname = 'fulltext';
        $vars['order'] = 4; // score
    } else {
        $funcname = 'nonfulltext';
        $vars['order'] = 2; // title
    }
    list($searchresults,
         $total_hits) =  pnModAPIFunc('pnForum', 'search', $funcname, $vars);

    $assign = (isset($assign)) ? $assign : 'similartopics';
    $smarty->assign($assign, $searchresults);
    return;

}

?>