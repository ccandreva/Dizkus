<?php

use Doctrine\ORM\Mapping as ORM;


/**
 * Favorites entity class.
 *
 * Annotations define the entity mappings to database.
 *
 * @ORM\Entity
 * @ORM\Table(name="dizkus_topics")
 */
class Dizkus_Entity_Topics extends Zikula_EntityAccess
{

    /**
     * The following are annotations which define the topic_id field.
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $topic_id;

    
    /**
     * The following are annotations which define the topic_id field.
     *
     * @ORM\Column(type="integer")
     */
    private $topic_poster;

    /**
     * The following are annotations which define the topic_title field.
     * 
     * @ORM\Column(type="string", length="255")
     */
    private $topic_title = '';
    
    /**
     * The following are annotations which define the topic time field.
     * 
     * @ORM\Column(type="string", length=20)
     */
    private $topic_time;
    
    
    
    /**
     * The following are annotations which define the topic_title field.
     * 
     * @ORM\Column(type="integer")
     */
    private $topic_status = 0;
    
    
    /**
     * The following are annotations which define the topic_title field.
     * 
     * @ORM\Column(type="integer", length=10)
     */
    private $topic_replies = 0;
    
    
    /**
     * The following are annotations which define the topic_title field.
     * 
     * @ORM\Column(type="boolean")
     */
    private $sticky = false;
    
    
    /**
     * The following are annotations which define the forum id field.
     * 
     * @ORM\Column(type="integer")
     */
    private $forum_id;
    
    
    public function gettopic_id()
    {
        return $this->topic_id;
    }
    
    public function gettopic_poster()
    {
        return $this->topic_poster;
    }
    
    public function gettopic_title()
    {
        return $this->topic_title;
    }
    
    public function gettopic_status()
    {
        return $this->topic_status;
    }
    
    
    public function gettopic_time()
    {
        return $this->topic_time;
    }
    
    
    public function gettopic_replies()
    {
        return $this->topic_replies;
    }
    
    
    public function getsticky()
    {
        return $this->sticky;
    }
    
    public function getforum_id()
    {
        return $this->forum_id;
    }
    
    
    public function lock()
    {
        $this->topic_status = 1;
    }
    
    public function unlock()
    {
        $this->topic_status = 0;
    }
    
    
    public function sticky()
    {
        $this->sticky = true;
    }
    
    public function unsticky()
    {
        $this->sticky = false;
    }

}