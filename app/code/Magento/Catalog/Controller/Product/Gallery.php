<?php
/**
 *
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Catalog\Controller\Product;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result;
use Magento\Framework\View\Result\PageFactory;

class Gallery extends \Magento\Catalog\Controller\Product
{
    /**
     * @var Result\Redirect
     */
    protected $resultRedirectFactory;

    /**
     * @var Result\ForwardFactory
     */
    protected $resultForwardFactory;

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * Constructor
     *
     * @param Context $context
     * @param Result\RedirectFactory $resultRedirectFactory
     * @param Result\ForwardFactory $resultForwardFactory
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        Result\RedirectFactory $resultRedirectFactory,
        Result\ForwardFactory $resultForwardFactory,
        PageFactory $resultPageFactory
    ) {
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->resultForwardFactory = $resultForwardFactory;
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    /**
     * View product gallery action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $result = null;
        if (!$this->_initProduct()) {
            if (isset($_GET['store']) && !$this->getResponse()->isRedirect()) {
                $result = $this->resultRedirectFactory->create();
                $result->setPath('');
            } elseif (!$this->getResponse()->isRedirect()) {
                $result = $this->resultForwardFactory->create();
                $result->forward('noroute');
            }
        }
        return $result ?: $this->resultPageFactory->create();
    }
}
