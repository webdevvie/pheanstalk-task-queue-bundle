<?php

namespace Webdevvie\PheanstalkTaskQueueBundle\Service\DTO;

use Pheanstalk\Job;
use Webdevvie\PheanstalkTaskQueueBundle\Entity\Task;
use Webdevvie\PheanstalkTaskQueueBundle\TaskDescription\TaskDescriptionInterface;


/**
 * Class WorkPackage
 * This class is used by the TaskQueueService and the WorkerCommand to handle workload for the worker. This
 * object is passed around between the TaskQueueService and the WorkerCommand.
 *
 * @package Webdevvie\PheanstalkTaskQueueBundle\Service\DTO
 * @author John Bakker <me@johnbakker.name>
 */
class WorkPackage
{
    /**
     * Contains the original task
     * @var Job
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
     * @param Job $job
     * @param TaskDescriptionInterface $taskDescription
     */
    public function __construct(Task $entity, Job $job, TaskDescriptionInterface $taskDescription)
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
     * Returns the saved Job
     *
     * @return Job
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
