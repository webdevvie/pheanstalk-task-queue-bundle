TaskQueue
=========

Beanstalkd
----------
Beanstalkd is a daemon that takes care of queueing. For a good idea of what Beanstalkd does, first read this:
http://alister.github.io/presentations/Beanstalkd/

Why use Beanstalk
~~~~~~~~~~~~~~~~~
We use beanstalk as a queue and to make sure only one worker picks up a piece of work. We chose Beanstalkd because of
the ease of set-up and the simplicity of the queue. It does what it says on the tin. The only issue is though that
it comes without any form of workers like for example Gearman. Which is what this bundle is for. This bundle provides
the workers and a service to make offloading your work to a background process fairly easy.


Flow of work
------------
The following diagram indicates the flow of a task through the queue/worker system. On the left hand side there is an
ExampleTaskDescription object. This object extends from AbstractTaskDescription and is given to the TaskQueueService.
The TaskQueueService is using the "testtube" tube (as per default config) From the ExampleTask two versions are stored.
One in the database for status updates and one in the Beanstalkd tube. Then the task-worker reserves a tasks from the
TaskQueueService and starts the child command to do the actual work. It also updates the status using the TaskQueueService.
After it is done the TaskQueueService is told to mark it done. At this point the task is deleted from the tube and the
status is set to done in the table. If the task Failed it is marked as failed in the table and deleted from the tube.

::

 +----------------------------------+
 |  ExampleTaskDescription          |                                                +--------------------------------------------------+
 |----------------------------------|                                                | task-worker-tender                               |
 | command= taskqueue:example-task-worker |                                                |--------------------------------------------------|
 |                                  |                                                | Keeps enough workers alive                       |
 +-----+----------------------------+                                                +--------+----------------------------+------------+
       |                                                                                      |                            |
       |         +---------------------------------------------+                     +--------+---------------+ +----------+------------+
       |         |   TaskQueueService                          |                     | task-worker            | | task-worker           |
       |         |---------------------------------------------|                     |------------------------| |-----------------------|
       +-------->| -queueTask                                  |                     |                        | |                       |
                 | -reserveTask <--------------------------------------------------->| +--------------------+ | |  Another worker       |
                 | -markDone <-----------------------------------------------------+ | | Work package       | | |                       |
                 | -markFailed <-------------------------------------------------+ | | |--------------------| | |                       |
                 | -regenerateTask                             |                 | | | | -Pheanstalk_Job    | | |                       |
                 |                                             |                 | | | | -TaskEntity        | | |                       |
                 +-------^-----------------------------^-------+                 | | | | -TaskDescription   | | +-----------------------+
                         |                             |                         | | | |                    | |
                         |                             |                         | | | |                    | |
                         v                             v                         | | | |                    | | +----------------------+
                 +----------------+          +--------------------+              | | | |                    | | | ExampleWorkerCommand |
                 | Task (Entity)  |          | Beanstalkd tube    |              | | | |                    | | |----------------------|
                 |----------------|          |--------------------|              | | | +--------------------+ | |                      |
                 |+--------------+|          |+------------------+|              | | |                        | |  Does its thing      |
                 || Task         ||          || Job              ||              | | |        Run+------------->|                      |
                 ||--------------|| related  ||------------------||              | | |                        | |                      |
                 ||id <----------------------->identifier:1      ||              | | |                        | |                      |
                 ||ExampleTask-  ||          ||ExampleTask-      ||              | | |                        | |                      |
                 ||Description   ||          ||Description       ||              | +----------+Done     <------+|                      |
                 ||              ||          |+------------------+|              +------------+Failed   <------+|                      |
                 ||              ||          |                    |                  |                        | |                      |
                 |+--------------+|          |                    |                  | Reserves a piece of    | |                      |
                 |                |          |                    |                  | work. After it is done | |                      |
                 |                |          |  Other jobs live   |                  | or failed, it will     | +----------------------+
                 |                |          |     here           |                  | report back to the     |
                 |                |          |                    |                  | service.               |
                 |                |          |                    |                  | Then continue reserving|
                 |                |          |                    |                  | other tasks            |
                 |                |          |                    |                  |                        |
                 |                |          |                    |                  |                        |
                 |                |          |                    |                  |                        |
                 |                |          |                    |                  |                        |
                 +----------------+          +--------------------+                  |                        |
                                                                                     +------------------------+



Task-worker-tender and task-worker
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
The following diagram shows the relation between the task-worker-tender and the task-worker.
The task-worker executes the actual symfony commands for the task and the task-worker-tender takes
care of having enough workers spooled up and ready to go.

::

  +------------------------------------------------+
  | task-worker-tender                             |
  |------------------------------------------------|
  |                                                |
  |                +---------------------------+   |    +--------------+   +--------------------------+
  |                | child-process-container   |   |    | task-worker  |   | symfony command          |
  |                |---------------------------|   |    |--------------|   |--------------------------|
  |                |                           |   |    |              |   |                          |
  |                | -Process                  |+------>|              +--->                          |
  |                |                           |   |    |              |   |                          |
  |                |                           |   |    |              |   |                          |
  |                |                           |<------+|              <---+                          |
  |                |                           |   |    |              |   |                          |
  |                |                           |   |    |              |   |                          |
  |                +---------------------------+   |    +--------------+   +--------------------------+
  |                                                |
  +------------------------------------------------+

Creating tasks
--------------
Say you would like to offload the creation of a pdf invoice and the emailing of said invoice.

Step 1: Identify the location of creation
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
Identify where all the information is available to create the invoice and make sure the data is persisted in the database
or whatever storage you use.


Step 2: Isolate your invoice pdf generation code
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
Make a method that can be called from your command (in step 3) to generate the pdf.
(move the code identified in step 1, there)

Step 3: Create a command
~~~~~~~~~~~~~~~~~~~~~~~~
Create a symfony2 command and have it receive an option or argument that does the work that needs to be done based on
the options and/or arguments. Make sure you send just an id or something similar. Sending the entire workload along via
command line is not advised.

Fill in your "Creation" and email code here and test if your command works by manually calling it using app/console

Check out PheanstalkTaskQueueBundle/Command/ExampleWorkerCommand.php for an example.

Step 4: Create a task
~~~~~~~~~~~~~~~~~~~~~
The task must implement TaskDescriptionInterface to be able to be handled by the TaskQueueService and its workers.
Make a class that extends from "Webdevvie\PheanstalkTaskQueueBundle\TaskDescription\AbstractTaskDescription" to have everything
you need ready to go. Then make sure you add your option/argument value properties to this task. Make sure you specify
the $command property with the command you just created. Also add the right options and/or parameters to the Task via
the $commandArguments and $commandOptions properties. Please check out the ExampleTask class to see how this works.
Finally make sure your properties are able to be serialized.

Step 5: Replace the location of creation
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
Use the task you just created it and pass it to the TaskQueueService also known as "webdevvie_pheanstalktaskqueue.service"
via the following call :
$taskQueueService->queueTask($yourTask);

The task is now offloaded to a background process.

Commands:
---------
The TaskQueue uses a few commands. One of these should be run as a cron job. (task-cleanup)
This is a list of all general commands and their usage.

taskqueue:task-worker-tender
~~~~~~~~~~~~~~~~~~~~~~~~~~~~
This tends to the needs of the workers. If you just need 1 worker you are good just starting taskqueue:taskworker
You can run multiple worker-tenders for different tubes.

+-------------------+--------------------------------------------------------------------------------+
|   option          | description                                                                    |
+===================+================================================================================+
|  worker-command   | The symfony command to execute for a worker                                    |
+-------------------+--------------------------------------------------------------------------------+
|  min-workers      | Minimal amount of workers to have running                                      |
+-------------------+--------------------------------------------------------------------------------+
|  spare-workers    | The amount of workers to keep spun up as spare                                 |
+-------------------+--------------------------------------------------------------------------------+
|  max-workers      | The maximum amount of workers to have                                          |
+-------------------+--------------------------------------------------------------------------------+
|  max-worker-age   | The maximum amount of time a worker should be working.                         |
+-------------------+--------------------------------------------------------------------------------+
|  max-worker-buffer| The maximum amount of output a worker should give before it is told to         |
|                   | stop working                                                                   |
+-------------------+--------------------------------------------------------------------------------+
|  use-tube         | This option is set to use the beanstalk tube supplied                          |
+-------------------+--------------------------------------------------------------------------------+

taskqueue:task-worker
~~~~~~~~~~~~~~~~~~~~~
This is the actual worker. This command will start reading from the Task Queue Service and starting up other
symfony2 commands.

+-------------------+--------------------------------------------------------------------------------+
|   option          | description                                                                    |
+===================+================================================================================+
|   use-tube        | This option is set to use the beanstalk tube supplied                          |
+-------------------+--------------------------------------------------------------------------------+

taskqueue:regenerate-failed-task
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
Regenerate failed tasks

+-------------------+--------------------------------------------------------------------------------+
|   option          | description                                                                    |
+===================+================================================================================+
|   id              | The id of the task to regenerate                                               |
+-------------------+--------------------------------------------------------------------------------+
|   use-tube        | This option is set to use the beanstalk tube supplied                          |
+-------------------+--------------------------------------------------------------------------------+

taskqueue:task-cleanup
~~~~~~~~~~~~~~~~~~~~~~
Cleans up tasks with the "done" status. Make this run once a day to keep things clean.

+-------------------+--------------------------------------------------------------------------------+
|   option          | description                                                                    |
+===================+================================================================================+
|   period          | everything older than supplied seconds and status done are to be deleted       |
+-------------------+--------------------------------------------------------------------------------+


taskqueue:add-example-task
~~~~~~~~~~~~~~~~~~~~~~~~~~
Adds an example task to the tube. Useful for testing if you set up your beanstalkd correctly.

+-------------------+--------------------------------------------------------------------------------+
|   option          | description                                                                    |
+===================+================================================================================+
|   total-tasks     | the amount of tasks to add to the tube                                         |
+-------------------+--------------------------------------------------------------------------------+

taskqueue:add-example-task
~~~~~~~~~~~~~~~~~~~~~~~~~~
Adds an example task to the tube. Useful for testing if you set up your beanstalkd correctly.

+-------------------+--------------------------------------------------------------------------------+
|   option          | description                                                                    |
+===================+================================================================================+
|   total-tasks     | the amount of tasks to add to the tube                                         |
+-------------------+--------------------------------------------------------------------------------+

taskqueue:example-task-worker
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
Does the example work triggered by add-example-task

Arguments

+-------------------+--------------------------------------------------------------------------------+
|   argument        | description                                                                    |
+===================+================================================================================+
|   wait            | the amount of time to wait in seconds before displaying the message            |
+-------------------+--------------------------------------------------------------------------------+

Options

+-------------------+--------------------------------------------------------------------------------+
|   option          | description                                                                    |
+===================+================================================================================+
|   message         | the message to display                                                         |
+-------------------+--------------------------------------------------------------------------------+