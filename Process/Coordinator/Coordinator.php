<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\FlowBundle\Process\Coordinator;

use Sylius\Bundle\FlowBundle\Process\Builder\ProcessBuilderInterface;
use Sylius\Bundle\FlowBundle\Process\Context\ProcessContextInterface;
use Sylius\Bundle\FlowBundle\Process\ProcessInterface;
use Sylius\Bundle\FlowBundle\Process\Scenario\ProcessScenarioInterface;
use Sylius\Bundle\FlowBundle\Process\Step\ActionResult;
use Sylius\Bundle\FlowBundle\Process\Step\StepInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use FOS\RestBundle\View\View;

/**
 * Default coordinator implementation.
 *
 * @author Paweł Jędrzejewski <pjedrzejewski@diweb.pl>
 */
class Coordinator implements CoordinatorInterface
{
    /**
     * Router.
     *
     * @var RouterInterface
     */
    protected $router;

    /**
     * Process builder.
     *
     * @var ProcessBuilderInterface
     */
    protected $builder;

    /**
     * Process context.
     *
     * @var ProcessContextInterface
     */
    protected $context;

    /**
     * Registered scenarios.
     *
     * @var array
     */
    protected $scenarios;

    /**
     * Constructor.
     *
     * @param RouterInterface         $router
     * @param ProcessBuilderInterface $builder
     * @param ProcessContextInterface $context
     */
    public function __construct(RouterInterface $router, ProcessBuilderInterface $builder, ProcessContextInterface $context)
    {
        $this->router = $router;
        $this->builder = $builder;
        $this->context = $context;

        $this->scenarios = array();
    }

    /**
     * {@inheritdoc}
     */
    public function start($scenarioAlias)
    {
        return $this->process($scenarioAlias, null, 'start');
    }

    /**
     * {@inheritdoc}
     */
    public function display($scenarioAlias, $stepName)
    {
        return $this->process($scenarioAlias, $stepName, 'display');
    }

    /**
     * {@inheritdoc}
     */
    public function forward($scenarioAlias, $stepName)
    {
        return $this->process($scenarioAlias, $stepName, 'forward');
    }

    public function processStepResult(ProcessInterface $process, $result)
    {
        if ($result instanceof Response) {
            return $result;
        }

        if ($result instanceof View) {
            return $result;
        }

        if ($result instanceof ActionResult) {
            // Handle explicit jump to step.
            if ($result->getNextStepName()) {
                $this->context->setNextStepByName($result->getNextStepName());

                return $this->redirectToStepDisplayAction($process, $this->context->getNextStep());
            }

            // Handle last step.
            if ($this->context->isLastStep()) {
                $this->context->close();

                $url = $this->router->generate($process->getRedirect());

                return new RedirectResponse($url);
            }

            // Handle default linear behaviour.
            return $this->redirectToStepDisplayAction($process, $this->context->getNextStep());
        }

        throw new \RuntimeException('Wrong action result, expected Response or ActionResult');
    }

    /**
     * {@inheritdoc}
     */
    public function registerScenario($alias, ProcessScenarioInterface $scenario)
    {
        if (isset($this->scenarios[$alias])) {
            throw new \InvalidArgumentException(sprintf('Process scenario with alias "%s" is already registered', $alias));
        }

        $this->scenarios[$alias] = $scenario;
    }

    /**
     * {@inheritdoc}
     */
    public function loadScenario($alias)
    {
        if (!isset($this->scenarios[$alias])) {
            throw new \InvalidArgumentException(sprintf('Process scenario with alias "%s" is not registered', $alias));
        }

        return $this->scenarios[$alias];
    }

    /**
     * Redirect to step display action.
     *
     * @param ProcessInterface $process
     * @param StepInterface    $step
     *
     * @return RedirectResponse
     */
    protected function redirectToStepDisplayAction(ProcessInterface $process, StepInterface $step)
    {
        $this->context->addStepToHistory($step->getName());

        if (null !== $route = $process->getDisplayRoute()) {
            $url = $this->router->generate($route, array(
                'stepName' => $step->getName()
            ));

            return new RedirectResponse($url);
        }

        $url = $this->router->generate('sylius_flow_display', array(
            'scenarioAlias' => $process->getScenarioAlias(),
            'stepName'      => $step->getName()
        ));

        return new RedirectResponse($url);
    }

    /**
     * @param string $scenarioAlias
     * @param string $stepName
     * @param string $action
     *
     * @return mixed
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    private function process($scenarioAlias, $stepName, $action)
    {
        $process = $this
            ->builder
            ->build($this->loadScenario($scenarioAlias))
        ;
        $process->setScenarioAlias($scenarioAlias);

        if (null === $stepName) {
            $step = $process->getFirstStep();
            $this->context->initialize($process, $step);
            $this->context->close();
        } else {
            $step = $process->getStepByName($stepName);
            $this->context->initialize($process, $step);

            if (!$this->context->rewindHistory()) {
                //history rewind failed this means that the rewind traversed whole history and didn't find
                //a step with a name we need. The best thing we could do here is to redirect user to a first step.
                $step = $process->getFirstStep();
                return $this->redirectToStepDisplayAction($process, $step);
            }
        }

        if (!$this->context->isValid()) {
            throw new NotFoundHttpException();
        }

        switch ($action) {
            case 'start':
                return $this->redirectToStepDisplayAction($process, $step);
                break;
            case 'display':
                $result = $step->displayAction($this->context);
                break;
            case 'forward';
                $result = $step->forwardAction($this->context);
                break;
            default:
                throw new \InvalidArgumentException(sprintf('%s is not a valid action parameter.', $action));
        }

        return $this->processStepResult($process, $result);
    }

}
