<?php

namespace Webdevvie\TaskQueueBundle\Service\DTO;

use Webdevvie\TaskQueueBundle\Entity\Task;
use Webdevvie\TaskQueueBundle\TaskDescription\TaskDescriptionInterface;
use \Pheanstalk_Job;

/**
 * Class WorkPackage
 * This class is used by the TaskQueueService and the WorkerCommand to handle workload for the worker. This
 * object is passed around between the TaskQueueService and the WorkerCommand.
 *
 * @package Webdevvie\TaskQueueBundle\Service\DTO
 * @author John Bakker <me@johnbakker.name
 */
class WorkPackage
{
    /**
     * Contains the original task
     * @var Pheanstalk_Job
     */
    protected $originalTask;

    /**
     * Contains the task entity
     * @var Task
     */
    protected $entity;

    /**
     * @var TaskDescriptionInterface
     */
    protected $taskDescription;

    /**
     * Constructs the WorkPackage class
     *
     * @param Task $entity
     * @param Pheanstalk_Job $job
     * @param TaskDescriptionInterface $taskDescription
     */
    public function __construct(Task $entity, Pheanstalk_Job $job, TaskDescriptionInterface $taskDescription)
    {
        $this->job = $job;
        $this->taskDescription=$taskDescription;
        $this->entity=$entity;
    }

    /**
     * Returns the task entity
     *
     * @return Task
     */
    public function getTaskEntity()
    {
        return $this->entity;
    }

    /**
     * Returns the saved Pheanstalk_Job
     *
     * @return Pheanstalk_Job
     */
    public function getPheanstalkJob()
    {
        return $this->job;
    }

    /**
     * @return TaskDescriptionInterface
     */
    public function getTaskDescription()
    {
        return $this->taskDescription;
    }
}
