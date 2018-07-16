<?php

namespace Webdevvie\PheanstalkTaskQueueBundle\TaskDescription;

use Pheanstalk\Job;
use Webdevvie\PheanstalkTaskQueueBundle\Entity\Task;

/**
 * The interface all task descriptions should adhere to.
 * Task descriptions are used to describe some of the work to be done on the backend.
 *
 * @package Webdevvie\PheanstalkTaskQueueBundle\TaskDescription
 * @author John Bakker <me@johnbakker.name>
 */
interface TaskDescriptionInterface
{
    /**
     * Returns the string for the command to execute
     *
     * @return string
     */
    public function getCommand();

    /**
     * Returns a two dimensional array of options to pass to the command
     * The key is the
     *
     * @return array
     */
    public function getOptions();

    /**
     * Returns a one dimensional array of arguments to pass to the command
     *
     * @return array
     */
    public function getArguments();

    /**
     * Returns the identifier for the task entity
     *
     * @return integer
     */
    public function getTaskIdentifier();

    /**
     * Sets the identifier for the task entity so it can be retrieved
     *
     * @param integer $identifier
     * @return void
     */
    public function setTaskIdentifier($identifier);
}
