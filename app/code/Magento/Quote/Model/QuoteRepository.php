<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Quote\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Framework\Store\StoreManagerInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Quote\Model\Resource\Quote\Collection as QuoteCollection;
use Magento\Framework\Exception\InputException;

class QuoteRepository implements \Magento\Quote\Api\CartRepositoryInterface
{
    /**
     * @var Quote[]
     */
    protected $quotesById = [];

    /**
     * @var Quote[]
     */
    protected $quotesByCustomerId = [];

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var \Magento\Framework\Store\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Quote\Model\Resource\Quote\Collection
     */
    protected $quoteCollection;

    /**
     * @var \Magento\Quote\Api\Data\CartSearchResultsDataBuilder
     */
    protected $searchResultsBuilder;

    /**
     * @param QuoteFactory $quoteFactory
     * @param StoreManagerInterface $storeManager
     * @param \Magento\Quote\Model\Resource\Quote\Collection $quoteCollection
     * @param \Magento\Quote\Api\Data\CartSearchResultsDataBuilder $searchResultsBuilder
     */
    public function __construct(
        QuoteFactory $quoteFactory,
        StoreManagerInterface $storeManager,
        \Magento\Quote\Model\Resource\Quote\Collection $quoteCollection,
        \Magento\Quote\Api\Data\CartSearchResultsDataBuilder $searchResultsBuilder
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->storeManager = $storeManager;
        $this->searchResultsBuilder = $searchResultsBuilder;
        $this->quoteCollection = $quoteCollection;
    }

    /**
     * Create new quote
     *
     * @param array $data
     * @return Quote
     */
    public function create(array $data = [])
    {
        return $this->quoteFactory->create($data);
    }

    /**
     * Get quote by id
     *
     * @param int $cartId
     * @param int[] $sharedStoreIds
     * @throws NoSuchEntityException
     * @return \Magento\Quote\Api\Data\CartInterface
     */
    public function get($cartId, array $sharedStoreIds = [])
    {
        if (!isset($this->quotesById[$cartId])) {
            $quote = $this->loadQuote('load', 'cartId', $cartId, $sharedStoreIds);
            $this->quotesById[$cartId] = $quote;
            $this->quotesByCustomerId[$quote->getCustomerId()] = $quote;
        }
        return $this->quotesById[$cartId];
    }

    /**
     * Get quote by customer Id
     *
     * @param int $customerId
     * @param int[] $sharedStoreIds
     * @return Quote
     * @throws NoSuchEntityException
     */
    public function getForCustomer($customerId, array $sharedStoreIds = [])
    {
        if (!isset($this->quotesByCustomerId[$customerId])) {
            $quote = $this->loadQuote('loadByCustomer', 'customerId', $customerId, $sharedStoreIds);
            $this->quotesById[$quote->getId()] = $quote;
            $this->quotesByCustomerId[$customerId] = $quote;
        }
        return $this->quotesByCustomerId[$customerId];
    }

    /**
     * Get active quote by id
     *
     * @param int $cartId
     * @param int[] $sharedStoreIds
     * @return Quote
     * @throws NoSuchEntityException
     */
    public function getActive($cartId, array $sharedStoreIds = [])
    {
        $quote = $this->get($cartId, $sharedStoreIds);
        if (!$quote->getIsActive()) {
            throw NoSuchEntityException::singleField('cartId', $cartId);
        }
        return $quote;
    }

    /**
     * Get active quote by customer Id
     *
     * @param int $customerId
     * @param int[] $sharedStoreIds
     * @return Quote
     * @throws NoSuchEntityException
     */
    public function getActiveForCustomer($customerId, array $sharedStoreIds = [])
    {
        $quote = $this->getForCustomer($customerId, $sharedStoreIds);
        if (!$quote->getIsActive()) {
            throw NoSuchEntityException::singleField('customerId', $customerId);
        }
        return $quote;
    }

    /**
     * Save quote
     *
     * @param Quote $quote
     * @return void
     */
    public function save(Quote $quote)
    {
        $quote->save();
        unset($this->quotesById[$quote->getId()]);
        unset($this->quotesByCustomerId[$quote->getCustomerId()]);
    }

    /**
     * Delete quote
     *
     * @param Quote $quote
     * @return void
     */
    public function delete(Quote $quote)
    {
        $quoteId = $quote->getId();
        $customerId = $quote->getCustomerId();
        $quote->delete();
        unset($this->quotesById[$quoteId]);
        unset($this->quotesByCustomerId[$customerId]);
    }

    /**
     * Load quote with different methods
     *
     * @param string $loadMethod
     * @param string $loadField
     * @param int $identifier
     * @param int[] $sharedStoreIds
     * @throws NoSuchEntityException
     * @return Quote
     */
    protected function loadQuote($loadMethod, $loadField, $identifier, array $sharedStoreIds = [])
    {
        /** @var Quote $quote */
        $quote = $this->quoteFactory->create();
        if ($sharedStoreIds) {
            $quote->setSharedStoreIds($sharedStoreIds);
        }
        $quote->setStoreId($this->storeManager->getStore()->getId())->$loadMethod($identifier);
        if (!$quote->getId()) {
            throw NoSuchEntityException::singleField($loadField, $identifier);
        }
        return $quote;
    }

    /**
     * {@inheritdoc}
     */
    public function getList(\Magento\Framework\Api\SearchCriteria $searchCriteria)
    {
        $this->searchResultsBuilder->setSearchCriteria($searchCriteria);

        foreach ($searchCriteria->getFilterGroups() as $group) {
            $this->addFilterGroupToCollection($group, $this->quoteCollection);
        }

        $this->searchResultsBuilder->setTotalCount($this->quoteCollection->getSize());
        $sortOrders = $searchCriteria->getSortOrders();
        if ($sortOrders) {
            foreach ($sortOrders as $sortOrder) {
                $this->quoteCollection->addOrder(
                    $sortOrder->getField(),
                    $sortOrder->getDirection() == SearchCriteria::SORT_ASC ? 'ASC' : 'DESC'
                );
            }
        }
        $this->quoteCollection->setCurPage($searchCriteria->getCurrentPage());
        $this->quoteCollection->setPageSize($searchCriteria->getPageSize());

        $this->searchResultsBuilder->setItems($this->quoteCollection->getItems());

        return $this->searchResultsBuilder->create();
    }

    /**
     * Adds a specified filter group to the specified quote collection.
     *
     * @param FilterGroup $filterGroup The filter group.
     * @param QuoteCollection $collection The quote collection.
     * @return void
     * @throws InputException The specified filter group or quote collection does not exist.
     */
    protected function addFilterGroupToCollection(FilterGroup $filterGroup, QuoteCollection $collection)
    {
        $fields = [];
        $conditions = [];
        foreach ($filterGroup->getFilters() as $filter) {
            $fields[] = $filter->getField();
            $condition = $filter->getConditionType() ? $filter->getConditionType() : 'eq';
            $conditions[] = [$condition => $filter->getValue()];
        }
        if ($fields) {
            $collection->addFieldToFilter($fields, $conditions);
        }
    }
}
