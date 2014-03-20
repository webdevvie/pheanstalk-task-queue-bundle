<?php

namespace Webdevvie\TaskQueueBundle\TaskDescription;

use Webdevvie\TaskQueueBundle\Entity\Task;
use JMS\Serializer\SerializerBuilder;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Expose;
use Pheanstalk_Job;

/**
 * Is a basic implementation of the TaskDescriptionInterface.
 * Takes care of some basic functionality like returning the right data so you
 * don't have to implement these in each task description
 *
 * @package Webdevvie\TaskQueueBundle\TaskDescription
 * @ExclusionPolicy("all")
 */
abstract class AbstractTaskDescription implements TaskDescriptionInterface
{

    /**
     * The console command to execute on the worker side
     * @var string
     */
    protected $command;

    /**
     * The arguments to pass to the console command
     * @var array
     */
    protected $commandArguments = array();

    /**
     * the options to pass to the console command
     * @var array
     */
    protected $commandOptions = array();

    /**
     * The task identifier record to refer to. This is exposed and public so it can be serialized.
     *
     * @var string
     * @Type("string")
     * @SerializedName("taskIdentifier")
     * @expose
     */
    public $taskIdentifier = '';

    /**
     * Returns the string for the command to execute
     *
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Returns a two dimensional array of options to pass to the command
     * The key is the
     *
     * @return array
     */
    public function getOptions()
    {
        $options = [];
        foreach ($this->commandOptions as $key => $value) {
            $options[$key]=$this->$value;
        }
        return $options;
    }

    /**
     * Returns a one dimensional array of arguments to pass to the command
     *
     * @return array
     */
    public function getArguments()
    {
        $arguments = [];
        foreach ($this->commandArguments as $argument) {
            $arguments[] = $this->$argument;
        }
        return $arguments;
    }

    /**
     * Returns the identifier for the task entity
     *
     * @return integer
     */
    public function getTaskIdentifier()
    {
        return $this->taskIdentifier;
    }
    /**
     * Sets the identifier for the task entity so it can be retrieved
     *
     * @param integer $identifier
     * @return void
     */
    public function setTaskIdentifier($identifier)
    {
        $this->taskIdentifier = $identifier;
    }
}
