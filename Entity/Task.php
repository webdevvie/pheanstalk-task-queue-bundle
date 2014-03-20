<?php

namespace Webdevvie\PheanstalkTaskQueueBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Webdevvie\PheanstalkTaskQueueBundle\TaskDescription\TaskDescriptionInterface;

/**
 * Task entity
 * Used by the TaskQueueService as place to store the status of the Tasks
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="Webdevvie\PheanstalkTaskQueueBundle\Entity\TaskRepository")
 */
class Task
{
    const STATUS_NEW = 'new';
    const STATUS_WORKING = 'working';
    const STATUS_STALLED = 'stalled';
    const STATUS_FAILED = 'failed';
    const STATUS_RESTARTED = 'restarted';
    const STATUS_DONE = 'done';

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * The full class name of the object stored inside
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=255)
     */
    private $type;

    /**
     * the serialized data of the stored object
     * @var string
     *
     * @ORM\Column(name="data", type="text")
     */
    private $data;

    /**
     * The tube this task was sent to
     * @var string
     *
     * @ORM\Column(name="tube", type="string", length=32)
     */
    private $tube;

    /**
     * The current status of the task (see the constants above)
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=32)
     */
    private $status;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime")
     */
    private $created;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="modified", type="datetime")
     */
    private $modified;

    /**
     * @param TaskDescriptionInterface $taskDescription
     * @param string $stringVersion
     * @param string $tube
     */
    public function __construct(TaskDescriptionInterface $taskDescription, $stringVersion, $tube)
    {
        $this->created = new \DateTime();
        $this->modified = new \DateTime();
        $this->status = Task::STATUS_NEW;
        $this->type=get_class($taskDescription);
        $this->data = $stringVersion;
        $this->tube = $tube;
    }
    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get type
     *
     * @return string 
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get tube
     *
     * @return string
     */
    public function getTube()
    {
        return $this->tube;
    }

    /**
     * Get data
     *
     * @return string 
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set status
     *
     * @param string $status
     * @return task
     */
    public function setStatus($status)
    {
        $this->status = $status;
        $this->modified = new \DateTime();
        return $this;
    }

    /**
     * Get status
     *
     * @return string 
     */
    public function getStatus()
    {
        return $this->status;
    }



    /**
     * Get created
     *
     * @return \DateTime 
     */
    public function getCreated()
    {
        return $this->created;
    }



    /**
     * Get modified
     *
     * @return \DateTime 
     */
    public function getModified()
    {
        return $this->modified;
    }
}
