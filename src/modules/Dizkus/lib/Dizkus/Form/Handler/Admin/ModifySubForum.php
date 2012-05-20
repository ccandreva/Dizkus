<?php
/**
 * Dizkus
 *
 * @copyright (c) 2001-now, Dizkus Development Team
 * @link https://github.com/zikula-modules/Dizkus
 * @license GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package Dizkus
 */

class Dizkus_Form_Handler_Admin_ModifySubForum extends Zikula_Form_AbstractHandler
{
    private $subforum;
    
    function initialize(Zikula_Form_View $view)
    {
        if (!SecurityUtil::checkPermission('Dizkus::', '::', ACCESS_ADMIN) ) {
            return LogUtil::registerPermissionError();
        }
        
        $id = FormUtil::getPassedValue('id');
        
        if ($id) {
            $view->assign('templatetitle', $this->__('Modify subforum'));
            $this->subforum = $this->entityManager->find('Dizkus_Entity_Subforums', $id);            
            if ($this->subforum) {
               $this->view->assign($this->subforum->toArray());
            } else {
                return LogUtil::registerError($this->__f('Article with id %s not found', $id));
            }
        } else {
            $view->assign('templatetitle', $this->__('Create subforum'));
        } 
        
        
        $mainforums0 = DBUtil::selectObjectArray('dizkus_forums', $where = 'WHERE is_subforum = 0');
        $mainforums  = array();
        foreach ($mainforums0 as $mainforum) {
            $mainforums[] = array(
                'value' => $mainforum['forum_id'],
                'text' => $mainforum['forum_name']
            );
        }
        $this->view->assign('mainforums', $mainforums);
                
        $this->view->caching = false;

        return true;
    }

    function handleCommand(Zikula_Form_View $view, &$args)
    {
        $url = ModUtil::url('Dizkus', 'admin', 'subforums' );
        if ($args['commandName'] == 'cancel') {
            return $view->redirect($url);
        }
        
        // check for valid form
        if (!$view->isValid()) {
            return false;
        }
        
        $data = $view->getValues();

        
        // switch between edit and create mode
        if (!$this->subforum) {
            $this->subforum = new Dizkus_Entity_Subforums();
        } 
        
        $mainforum   = DBUtil::selectObjectByID ('dizkus_forums', $data['is_subforum'], 'forum_id');
        $data['cat_id'] = $mainforum['cat_id'];
        
        
        $this->subforum->merge($data);
        $this->entityManager->persist($this->subforum);
        $this->entityManager->flush();

        return $view->redirect($url);
    }
}
