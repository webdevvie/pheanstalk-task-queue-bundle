<?php

namespace Webdevvie\TaskQueueBundle\Command\Example\TaskDescription;

use Webdevvie\TaskQueueBundle\TaskDescription\AbstractTaskDescription;
use Webdevvie\TaskQueueBundle\TaskDescription\TaskDescriptionInterface;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\ExclusionPolicy;

/**
 * Class ExampleTask
 * Please check out the ExampleWorkerCommand.php in the command directory
 *
 * @package Webdevvie\TaskQueueBundle\TaskDescription
 * @author John Bakker <me@johnbakker.name
 * @ExclusionPolicy("all")
 */
class ExampleTaskDescription extends AbstractTaskDescription
{
    /**
     * the message to be given back
     *
     * @var string
     * @Type("string")
     * @SerializedName("message")
     * @expose
     */
    public $message;

    /**
     * The wait time before giving the message
     *
     * @var string
     * @Type("string")
     * @SerializedName("wait")
     * @expose
     */
    public $wait;

    /**
     * The command to pass to the console
     * @var string
     */
    protected $command = 'taskqueue:example-task-worker';

    /**
     * the options to pass to the console command
     * @var array
     */
    protected $commandOptions = ["message" => "message"];

    /**
     * The arguments to pass to the console command
     * @var array
     */
    protected $commandArguments = ["wait"];
}
