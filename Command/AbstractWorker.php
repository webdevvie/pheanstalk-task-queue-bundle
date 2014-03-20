<?php
namespace Webdevvie\PheanstalkTaskQueueBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webdevvie\PheanstalkTaskQueueBundle\Service\TaskQueueService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class AbstractWorker
 * Is a base for the other commands. A place to add options that should be used for all workers.
 * It simplifies the implementation of your own tasks and handling of signals for looping processes.
 *
 * @package Webdevvie\PheanstalkTaskQueueBundle\Command
 * @author John Bakker <me@johnbakker.name>
 */
abstract class AbstractWorker extends ContainerAwareCommand
{
    /**
     * @var \Webdevvie\PheanstalkTaskQueueBundle\Service\TaskQueueService
     */
    protected $taskQueueService;

    /**
     * If the worker needs to keep working this will stay true
     *
     * @var boolean
     */
    protected $keepWorking = true;

    /**
     * if the worker needs to shutdown immediately this will become true
     *
     * @var boolean
     */
    protected $shutdownNow = false;

    /**
     * If the worker needs to stop working after it is done
     *
     * @var boolean
     */
    protected $shutdownGracefully = false;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * the path where to find the console script
     *
     * @var string
     */
    protected $consolePath;

    /**
     * The tube to use
     *
     * @var string
     */
    protected $tube;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Set to true if the verbose messages should be sent to the output. Must be public ,used for childs as well
     *
     * @var boolean
     */
    protected $verbose;

    /**
     * Adds default configuration options and or arguments to the command it extends from
     *
     * @return void
     */
    protected function addDefaultConfiguration()
    {
        $this->addOption('use-tube', null, InputOption::VALUE_OPTIONAL, 'What tube to send to');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function initialiseWorker(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
        $this->output = $output;
        $this->input = $input;
        $this->verbose = $input->getOption('verbose');
        $this->container = $this->getContainer();
        $this->logger = $this->container->get('logger');
        $this->consolePath = $this->container->get('kernel')->getRootDir().'/../';
        $this->taskQueueService = $this->container->get('webdevvie_pheanstalktaskqueue.service');
        $this->tube = $this->input->getOption('use-tube');
        if (strlen($this->tube) == 0) {
            $this->tube = $this->taskQueueService->getDefaultTube();
        }
        //make sure signals are respected
        declare(ticks = 1);
        pcntl_signal(SIGTERM, array($this, "sigTerm"));
        pcntl_signal(SIGINT, array($this, "sigInt"));
    }

    /**
     * Logs an exception to the logger
     *
     * @param \Exception $exception
     * @return void
     */
    protected function logException(\Exception $exception)
    {
        $this->logger->error(
            $exception->getMessage(),
            array(
                'class'=>get_class($exception),
                'message'=>$exception->getMessage(),
                'file'=>$exception->getMessage(),
                'line'=>$exception->getMessage(),
                'command'=>$this->getName()
            )
        );
    }


    /**
     * Gracefully handle the signal to terminate as soon as possible
     *
     * @return void
     */
    public function sigTerm()
    {
        $this->shutdownGracefully=true;
        $this->verboseOutput("<info>Received Shutdown signal</info>");
    }

    /**
     * We're being told the process should shutdown now (ctrl+c)
     *
     * @return void
     */
    public function sigInt()
    {
        $this->shutdownNow=true;
        $this->shutdownGracefully=true;
        $this->verboseOutput("<info>Received Interrupt signal</info>");
    }

    /**
     * Outputs the string if verbose mode is on
     *
     * @param string $string
     * @return void
     */
    public function verboseOutput($string)
    {
        if ($this->isVerbose()) {
            $this->output->writeLn($string);
        }
    }

    /**
     * Returns true if there should be verbose output
     *
     * @return boolean
     */
    public function isVerbose()
    {
        return $this->verbose;
    }
}
