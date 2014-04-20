<?php

namespace Webdevvie\PheanstalkTaskQueueBundle\Service;

use Webdevvie\PheanstalkTaskQueueBundle\Service\Exception\TaskCommandGeneratorException;
use Webdevvie\PheanstalkTaskQueueBundle\TaskDescription\TaskDescriptionInterface;
use Webdevvie\PheanstalkTaskQueueBundle\Service\DTO\WorkPackage;

/**
 * Generates the command string from a WorkPackage's TaskDescription property
 * that workers use to start the work they need to do.
 *
 * @author John Bakker <me@johnbakker.name>
 */
class TaskCommandGenerator
{
    /**
     * Generates a symfony2 console command to execute from the task
     *
     * @param WorkPackage $work
     * @throws TaskCommandGeneratorException
     * @return string
     */

    public function generate(WorkPackage $work)
    {
        $description = $work->getTaskDescription();
        $command =$description->getCommand();
        if (!$this->isValidCommand($command)) {
            throw new TaskCommandGeneratorException("This command is not usable '{$command}' is not valid");
        }
        $cmd = escapeshellarg($command);
        $cmd .= $this->generateArguments($description);
        $cmd .= $this->generateOptions($description);
        return $cmd;
    }

    /**
     * Generates the argument part of the command
     *
     * @param TaskDescriptionInterface $description
     * @return string
     */
    private function generateArguments(TaskDescriptionInterface $description)
    {
        $cmdPart = '';
        $arguments = $description->getArguments();
        if (!is_array($arguments)) {
            return $cmdPart;
        }
        foreach ($arguments as $argumentValue) {
            if (is_string($argumentValue) || is_integer($argumentValue)) {
                $cmdPart .= ' ' . escapeshellarg($argumentValue);
            }
        }
        return $cmdPart;
    }

    /**
     * Generates the options part of the command
     *
     * @param TaskDescriptionInterface $description
     * @return string
     */
    private function generateOptions(TaskDescriptionInterface $description)
    {
        $cmdPart = '';
        $options = $description->getOptions();
        if (!is_array($options)) {
            return $cmdPart;
        }
        foreach ($options as $optionName => $optionValue) {
            //validate the option name
            if (!$this->isValidOptionName($optionName)) {
                continue;
            }
            if (is_bool($optionValue) && $optionValue) {
                $cmdPart .= ' --' . $optionName;
            } elseif (is_string($optionValue)|| is_integer($optionValue)) {
                $cmdPart .= ' --' . $optionName . '=' . escapeshellarg($optionValue);
            }
        }
        return $cmdPart;

    }

    /**
     * Matches true if the command is okay
     *
     * @param string $command
     * @return boolean
     */
    private function isValidCommand($command)
    {
        return (preg_match('/^[a-zA-Z0-9_\-\:]+$/', $command) == 1);
    }

    /**
     * validates if an option name matches the rules for options
     *
     * @param string $optionName
     * @return boolean
     */
    private function isValidOptionName($optionName)
    {
        return (preg_match('/^[a-zA-Z0-9_\-]+$/', $optionName) == 1);
    }
}
