<?php
namespace ZktSn0w\Inertia\Controller;

use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\View\ViewInterface;
use Neos\Fusion\View\FusionView;
use ZktSn0w\Inertia\Service\Inertia;

abstract class AbstractInertiaController extends ActionController
{
    protected $defaultViewObjectName = FusionView::class;

    #[Flow\Inject]
    protected Inertia $inertia;

    /**
     * @var FusionView
     */
    protected $view;

    #[Flow\InjectConfiguration(path: "fusion.fusionPathPatterns", package: "ZktSn0w.Inertia")]
    protected array $fusionPathPatterns;

    protected function initializeView(ViewInterface $view)
    {
        if ($view instanceof FusionView) {
            if (is_array(value: $this->fusionPathPatterns)) {
                $view->setFusionPathPatterns($this->fusionPathPatterns);
            }
        }
        parent::initializeView($view);
    }
}
