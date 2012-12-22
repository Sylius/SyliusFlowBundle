<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\FlowBundle\Process\Context;

use Sylius\Bundle\FlowBundle\Process\ProcessInterface;
use Sylius\Bundle\FlowBundle\Process\Step\StepInterface;
use Sylius\Bundle\FlowBundle\Storage\StorageInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Interface for process context.
 *
 * @author Paweł Jędrzejewski <pjedrzejewski@diweb.pl>
 */
interface ProcessContextInterface
{

    /**
     * Initialize context with process and current step.
     *
     * @param ProcessInterface $process
     * @param StepInterface    $currentStep
     */
    function initialize(ProcessInterface $process, StepInterface $currentStep);

    /**
     * Get process.
     *
     * @return ProcessInterface
     */
    function getProcess();

    /**
     * Get current step.
     *
     * @return StepInterface
     */
    function getCurrentStep();

    /**
     * Get previous step.
     *
     * @return StepInterface
     */
    function getPreviousStep();

    /**
     * Get next step.
     *
     * @return StepInterface
     */
    function getNextStep();

    /**
     * Is current step the first step?
     *
     * @return Boolean
     */
    function isFirstStep();

    /**
     * Is current step the last step?
     *
     * @return Boolean
     */
    function isLastStep();

    /**
     * Override the default next step.
     */
    function setNextStepByName($stepAlias);

    /**
     * Close context and clear all the data.
     */
    function close();

    /**
     * Is current flow valid?
     *
     * @return Boolean
     */
    function isValid();

    /**
     * Get storage.
     *
     * @return StorageInterface
     */
    function getStorage();

    /**
     * Set storage.
     *
     * @param StorageInterface $storage
     */
    function setStorage(StorageInterface $storage);

    /**
     * Get current request.
     *
     * @return Request
     */
    function getRequest();

    /**
     * Set current request.
     *
     * @param Request $request
     */
    function setRequest(Request $request);

    /**
     * Get progress in percents.
     *
     * @return integer
     */
    function getProgress();

    /**
     * The array contains the history of all the step names.
     *
     * @return array()
     */
    function getStepHistory();

    /**
     * Set a new history of step names.
     *
     * @param array $history
     */
    function setStepHistory(array $history);

    /**
     * Add the given name to the history of step names.
     *
     * @param string $stepName
     */
    function addStepToHistory($stepName);

    /**
     * Goes back from the end fo the history and deletes all step names until the current one is found.
     *
     * @return boolean
     */
    function rewindHistory();

}
