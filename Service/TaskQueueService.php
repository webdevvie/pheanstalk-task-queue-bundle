<?php

namespace Webdevvie\PheanstalkTaskQueueBundle\Service;

use JMS\Serializer\Exception\UnsupportedFormatException;
use Leezy\PheanstalkBundle\Proxy\PheanstalkProxy;
use JMS\Serializer\Serializer;
use Webdevvie\PheanstalkTaskQueueBundle\Service\DTO\WorkPackage;
use Webdevvie\PheanstalkTaskQueueBundle\TaskDescription\TaskDescriptionInterface;
use Webdevvie\PheanstalkTaskQueueBundle\Service\Exception\TaskQueueServiceException;
use Doctrine\ORM\EntityManager;
use Webdevvie\PheanstalkTaskQueueBundle\Entity\Task;
use Webdevvie\PheanstalkTaskQueueBundle\Entity\TaskRepository;

/**
 * Handles all communication with Beanstalkd queue and the task entity
 * This service is where you send your TaskDescriptions to be offloaded to the back end.
 * This service also is the contact point for workers to get new tasks or send back the task's status
 *
 * @author John Bakker <me@johnbakker.name
 */
class TaskQueueService
{
    /**
     * @var \Leezy\PheanstalkBundle\Proxy\PheanstalkProxy
     */
    private $beanstalk;

    /**
     * @var \JMS\Serializer\Serializer
     */
    private $serializer;

    /**
     * contains the default tube to use
     * @var string
     */
    private $defaultTube;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    /**
     * @var \Webdevvie\PheanstalkTaskQueueBundle\Entity\TaskRepository
     */
    private $taskRepo;


    /**
     * @param \Doctrine\ORM\EntityManager $entityManager
     * @param \Leezy\PheanstalkBundle\Proxy\PheanstalkProxy $beanstalk
     * @param \JMS\Serializer\Serializer $serializer
     * @param array $params
     */
    public function __construct(
        \Doctrine\ORM\EntityManager $entityManager,
        \Leezy\PheanstalkBundle\Proxy\PheanstalkProxy $beanstalk,
        \JMS\Serializer\Serializer $serializer,
        array $params
    ) {
        $this->beanstalk = $beanstalk;
        $this->serializer = $serializer;
        $this->entityManager = $entityManager;
        $this->taskRepo = $this->entityManager->getRepository('WebdevviePheanstalkTaskQueueBundle:Task');
        $this->defaultTube = $params['default_tube'];
    }

    /**
     * Returns the default tube name
     *
     * @return string
     */
    public function getDefaultTube()
    {
        return $this->defaultTube;
    }

    /**
     * Returns the current status of a task
     *
     * @param integer $taskId
     * @return string
     */
    public function getStatusOfTaskWithId($taskId)
    {
        $task = $this->taskRepo->find($taskId);
        if(!($task instanceof Task))
        {
            //the task was not found
            return Task::STATUS_GONE;
        }
        return $task->getStatus();
    }

    /**
     * @param TaskDescriptionInterface $task
     * @param string $tube (optional) the tube to use
     * @throws TaskQueueServiceException
     * @return integer
     */
    public function queueTask(TaskDescriptionInterface $task, $tube = null)
    {
        if (is_null($tube)) {
            $tube = $this->defaultTube;
        }
        $stringVersion = get_class($task) . '::' . $this->serializer->serialize($task, 'json');
        $taskEntity = new Task($task, $stringVersion, $tube);
        $this->entityManager->persist($taskEntity);
        $this->entityManager->flush($taskEntity);
        $task->setTaskIdentifier($taskEntity->getId());
        //regenerate it now we have an identifier
        $stringVersion = get_class($task) . '::' . $this->serializer->serialize($task, 'json');
        $this->beanstalk
            ->useTube($tube)
            ->put($stringVersion);

        return $taskEntity->getId();
    }

    /**
     * Regenerates a task (requeues it in the proper tube)
     *
     * @param integer $id
     * @return boolean
     * @throws TaskQueueServiceException
     */
    public function regenerateTask($id)
    {
        $taskEntity = $this->taskRepo->find($id);
        if (!($taskEntity instanceof Task)) {
            throw new TaskQueueServiceException("No such task :" . $id, 1);
        }
        if ($taskEntity->getStatus() != Task::STATUS_FAILED) {
            throw new TaskQueueServiceException(
                "Invalid status for task. Current status: '" .
                $taskEntity->getStatus() .
                "' must be 'failed' to be able to restart it",
                1
            );
        }
        $data = $taskEntity->getData();
        $taskEntity->setStatus(Task::STATUS_RESTARTED);
        $this->entityManager->flush();
        $parts = explode("::", $data, 2);
        $taskObject = $this->serializer->deserialize($parts[1], $parts[0], 'json');
        $taskObject->setTaskIdentifier($taskEntity->getId());
        $data = get_class($taskObject) . '::' . $this->serializer->serialize($taskObject, 'json');
        $this->beanstalk
            ->useTube($taskEntity->getTube())
            ->put($data);
        return true;
    }

    /**
     * Cleans up tasks that are marked as done
     *
     * @param integer $timePeriod
     * @return void
     */
    public function cleanUpTasks($timePeriod)
    {
        $query = $this->entityManager->createQuery(
            'DELETE
            FROM WebdevviePheanstalkTaskQueueBundle:Task t
            WHERE
            t.status = :status and
            t.created <= :older'
        );
        $query->setParameter('status', array(Task::STATUS_DONE));
        $query->setParameter('older', date("Y-m-d H:i:s", time()-$timePeriod));
        $query->execute();
    }

    /**
     * Returns a task to work on.
     *
     * @param string $tube (optional) the tube(queue) to reserve a task from
     *
     * @throws TaskQueueServiceException
     * @return WorkPackage
     */
    public function reserveTask($tube = null)
    {
        if (is_null($tube)) {
            $tube = $this->defaultTube;
        }
        $inTask = $this->beanstalk
            ->watch($tube)
            ->ignore('default')
            ->reserve(2);
        if ($inTask === false) {
            return null;
        }
        $data = $inTask->getData();
        $parts = explode("::", $data, 2);
        if (count($parts) !== 2) {
            $this->beanstalk->delete($inTask);
            throw new TaskQueueServiceException("Invalid format in TaskQueue {$tube}");
        }
        try {
            $taskObject = $this->serializer->deserialize($parts[1], $parts[0], 'json');
        } catch (UnsupportedFormatException $exception) {
            $this->beanstalk->delete($inTask);
            throw new TaskQueueServiceException("Invalid format in TaskQueue {$tube} ");
        } catch (\ReflectionException $exception) {
            $this->beanstalk->delete($inTask);
            throw new TaskQueueServiceException("Invalid format in TaskQueue {$tube} class ".$parts[0].' is unknown');
        }
        if (!($taskObject instanceof TaskDescriptionInterface)) {
            $this->beanstalk->delete($inTask);
            throw new TaskQueueServiceException("Invalid data in TaskQueue {$tube}");
        }
        $taskEntity = $this->taskRepo->find($taskObject->getTaskIdentifier());
        if (!($taskEntity instanceof Task)) {
            $this->beanstalk->delete($inTask);
            throw new TaskQueueServiceException(
                "Unable to find taskEntity for task:" . $taskObject->getTaskIdentifier()
            );
        }
        /**
         * @var Task $taskEntity
         */
        $workPackage = new WorkPackage($taskEntity, $inTask, $taskObject);
        $this->updateTaskStatus($workPackage, Task::STATUS_WORKING);
        return $workPackage;
    }

    /**
     * Deletes a task from the queue
     *
     * @param WorkPackage $task
     * @throws TaskQueueServiceException
     * @return void
     */
    public function markDone(WorkPackage $task)
    {
        $this->updateTaskStatus($task, Task::STATUS_DONE);
        $this->beanstalk->delete($task->getPheanstalkJob());
    }

    /**
     * Marks a job as failed and deletes it from the beanstalk tube
     *
     * @param WorkPackage $task
     * @throws TaskQueueServiceException
     * @return void
     */
    public function markFailed(WorkPackage $task)
    {
        $this->updateTaskStatus($task, Task::STATUS_FAILED);
        $this->beanstalk->delete($task->getPheanstalkJob());
    }

    /**
     * @param WorkPackage $task
     * @param string $status
     * @return void
     * @throws TaskQueueServiceException
     */
    private function updateTaskStatus(WorkPackage $task, $status)
    {
        $taskEntity = $task->getTaskEntity();
        if ($taskEntity instanceof Task) {
            $taskEntity->setStatus($status);
            //make sure it is stored...
            $this->entityManager->flush($taskEntity);
        } else {
            throw new TaskQueueServiceException("Entity is not of type Task");
        }
    }
}
