<?php

declare(strict_types=1);

namespace Linio\SellerCenter\Service;

use DateTimeInterface;
use Linio\Component\Util\Json;
use Linio\SellerCenter\Application\Parameters;
use Linio\SellerCenter\Contract\OrderSortDirections;
use Linio\SellerCenter\Contract\OrderSortFilters;
use Linio\SellerCenter\Contract\OrderStatus;
use Linio\SellerCenter\Exception\EmptyArgumentException;
use Linio\SellerCenter\Exception\InvalidDomainException;
use Linio\SellerCenter\Factory\Xml\Order\FailureReasonsFactory;
use Linio\SellerCenter\Factory\Xml\Order\OrderFactory;
use Linio\SellerCenter\Factory\Xml\Order\OrderItemsFactory;
use Linio\SellerCenter\Factory\Xml\Order\OrdersFactory;
use Linio\SellerCenter\Factory\Xml\Order\OrdersItemsFactory;
use Linio\SellerCenter\Model\Order\FailureReason;
use Linio\SellerCenter\Model\Order\Order;
use Linio\SellerCenter\Model\Order\OrderItem;

class OrderManager extends BaseManager
{
    public const DEFAULT_LIMIT = 1000;
    public const DEFAULT_OFFSET = 0;
    public const DEFAULT_SORT_BY = 'created_at';
    public const DEFAULT_SORT_DIRECTION = 'ASC';
    private const GET_ORDER_ACTION = 'GetOrder';
    private const GET_ORDER_ITEMS_ACTION = 'GetOrderItems';
    private const GET_MULTIPLE_ORDER_ITEMS_ACTION = 'GetMultipleOrderItems';
    private const GET_ORDERS = 'GetOrders';
    private const SET_STATUS_TO_PACKED_BY_MARKETPLACE_ACTION = 'SetStatusToPackedByMarketplace';
    private const SET_STATUS_TO_READY_TO_SHIP_ACTION = 'SetStatusToReadyToShip';
    private const SET_STATUS_TO_CANCELED_ACTION = 'SetStatusToCanceled';
    private const GET_FAILURES_REASON_ACTION = 'GetFailureReasons';

    public function getOrder(int $orderId): Order
    {
        $action = self::GET_ORDER_ACTION;

        $parameters = clone $this->parameters;
        $parameters->set([
            'OrderId' => $orderId,
        ]);

        $requestId = $this->generateRequestId();

        $builtResponse = $this->executeAction($action, $requestId, $parameters);

        $orderResponse = OrderFactory::make($builtResponse->getBody()->Orders->Order);

        $this->logger->info(
            sprintf(
                '%d::%s::APIResponse::SellerCenterSdk: the order was recovered',
                $requestId,
                $action
            )
        );

        return $orderResponse;
    }

    /**
     * @return OrderItem[]
     */
    public function getOrderItems(int $orderId): array
    {
        $action = self::GET_ORDER_ITEMS_ACTION;

        $parameters = clone $this->parameters;
        $parameters->set([
            'OrderId' => $orderId,
        ]);

        $requestId = $this->generateRequestId();

        $builtResponse = $this->executeAction($action, $requestId, $parameters);

        $orderItems = OrderItemsFactory::make($builtResponse->getBody());

        $orderItemsResponse = array_values($orderItems->all());

        $this->logger->info(
            sprintf(
                '%d::%s::APIResponse::SellerCenterSdk: %d order items was recovered',
                $requestId,
                $action,
                count($orderItems->all())
            )
        );

        return $orderItemsResponse;
    }

    /**
     * @return Order[]
     */
    public function getMultipleOrderItems(array $orderIdList): array
    {
        $action = self::GET_MULTIPLE_ORDER_ITEMS_ACTION;

        $parameters = clone $this->parameters;

        if (empty($orderIdList)) {
            throw new EmptyArgumentException('OrderIdList');
        }

        $parameters->set([
            'OrderIdList' => Json::encode($orderIdList),
        ]);

        $requestId = $this->generateRequestId();

        $builtResponse = $this->executeAction($action, $requestId, $parameters);

        $orderItems = OrdersItemsFactory::make($builtResponse->getBody());

        $multipleOrderItemsResponse = array_values($orderItems->all());

        $this->logger->info(
            sprintf(
                '%d::%s::APIResponse::SellerCenterSdk: %d orders items was recovered',
                $requestId,
                $action,
                count($orderItems->all())
            )
        );

        return $multipleOrderItemsResponse;
    }

    protected function getOrders(Parameters $parameters): array
    {
        $action = self::GET_ORDERS;

        $requestId = $this->generateRequestId();

        $builtResponse = $this->executeAction($action, $requestId, $parameters);

        $orders = OrdersFactory::make($builtResponse->getBody());

        $ordersResponse = array_values($orders->all());

        $this->logger->info(
            sprintf(
                '%d::%s::APIResponse::SellerCenterSdk: %d orders was recovered',
                $requestId,
                $action,
                count($orders->all())
            )
        );

        return $ordersResponse;
    }

    /**
     * @return Order[]
     */
    public function getOrdersCreatedBetween(
        DateTimeInterface $createdAfter,
        DateTimeInterface $createdBefore,
        int $limit = self::DEFAULT_LIMIT,
        int $offset = self::DEFAULT_OFFSET,
        string $sortBy = self::DEFAULT_SORT_BY,
        string $sortDirection = self::DEFAULT_SORT_DIRECTION
    ): array {
        $parameters = clone $this->parameters;

        $this->setListDimensions($parameters, $limit, $offset);
        $this->setSortParametersList($parameters, $sortBy, $sortDirection);

        $parameters->set([
            'CreatedAfter' => $createdAfter->format('Y-m-d\TH:i:s'),
            'CreatedBefore' => $createdBefore->format('Y-m-d\TH:i:s'),
        ]);

        return $this->getOrders($parameters);
    }

    /**
     * @return Order[]
     */
    public function getOrdersUpdatedBetween(
        DateTimeInterface $updatedAfter,
        DateTimeInterface $updatedBefore,
        int $limit = self::DEFAULT_LIMIT,
        int $offset = self::DEFAULT_OFFSET,
        string $sortBy = self::DEFAULT_SORT_BY,
        string $sortDirection = self::DEFAULT_SORT_DIRECTION
    ): array {
        $parameters = clone $this->parameters;

        $this->setListDimensions($parameters, $limit, $offset);
        $this->setSortParametersList($parameters, $sortBy, $sortDirection);

        $parameters->set([
            'UpdatedAfter' => $updatedAfter->format('Y-m-d\TH:i:s'),
            'UpdatedBefore' => $updatedBefore->format('Y-m-d\TH:i:s'),
        ]);

        return $this->getOrders($parameters);
    }

    /**
     * @return Order[]
     */
    public function getOrdersCreatedAfter(
        DateTimeInterface $createdAfter,
        int $limit = self::DEFAULT_LIMIT,
        int $offset = self::DEFAULT_OFFSET,
        string $sortBy = self::DEFAULT_SORT_BY,
        string $sortDirection = self::DEFAULT_SORT_DIRECTION
    ): array {
        $parameters = clone $this->parameters;

        $this->setListDimensions($parameters, $limit, $offset);
        $this->setSortParametersList($parameters, $sortBy, $sortDirection);

        $parameters->set([
            'CreatedAfter' => $createdAfter->format('Y-m-d\TH:i:s'),
        ]);

        return $this->getOrders($parameters);
    }

    /**
     * @return Order[]
     */
    public function getOrdersCreatedBefore(
        DateTimeInterface $createdBefore,
        int $limit = self::DEFAULT_LIMIT,
        int $offset = self::DEFAULT_OFFSET,
        string $sortBy = self::DEFAULT_SORT_BY,
        string $sortDirection = self::DEFAULT_SORT_DIRECTION
    ): array {
        $parameters = clone $this->parameters;

        $this->setListDimensions($parameters, $limit, $offset);
        $this->setSortParametersList($parameters, $sortBy, $sortDirection);

        $parameters->set([
            'CreatedBefore' => $createdBefore->format('Y-m-d\TH:i:s'),
        ]);

        return $this->getOrders($parameters);
    }

    /**
     * @return Order[]
     */
    public function getOrdersUpdatedAfter(
        DateTimeInterface $updatedAfter,
        int $limit = self::DEFAULT_LIMIT,
        int $offset = self::DEFAULT_OFFSET,
        string $sortBy = self::DEFAULT_SORT_BY,
        string $sortDirection = self::DEFAULT_SORT_DIRECTION
    ): array {
        $parameters = clone $this->parameters;

        $this->setListDimensions($parameters, $limit, $offset);
        $this->setSortParametersList($parameters, $sortBy, $sortDirection);

        $parameters->set([
            'UpdatedAfter' => $updatedAfter->format('Y-m-d\TH:i:s'),
        ]);

        return $this->getOrders($parameters);
    }

    /**
     * @return Order[]
     */
    public function getOrdersUpdatedBefore(
        DateTimeInterface $updatedBefore,
        int $limit = self::DEFAULT_LIMIT,
        int $offset = self::DEFAULT_OFFSET,
        string $sortBy = self::DEFAULT_SORT_BY,
        string $sortDirection = self::DEFAULT_SORT_DIRECTION
    ): array {
        $parameters = clone $this->parameters;

        $this->setListDimensions($parameters, $limit, $offset);
        $this->setSortParametersList($parameters, $sortBy, $sortDirection);

        $parameters->set([
            'UpdatedBefore' => $updatedBefore->format('Y-m-d\TH:i:s'),
        ]);

        return $this->getOrders($parameters);
    }

    /**
     * @return Order[]
     */
    public function getOrdersWithStatus(
        string $status,
        int $limit = self::DEFAULT_LIMIT,
        int $offset = self::DEFAULT_OFFSET,
        string $sortBy = self::DEFAULT_SORT_BY,
        string $sortDirection = self::DEFAULT_SORT_DIRECTION
    ): array {
        $parameters = clone $this->parameters;

        $this->setListDimensions($parameters, $limit, $offset);
        $this->setSortParametersList($parameters, $sortBy, $sortDirection);

        if (!in_array($status, OrderStatus::STATUS)) {
            throw new InvalidDomainException('Status');
        }

        $parameters->set([
            'Status' => $status,
        ]);

        return $this->getOrders($parameters);
    }

    /**
     * @return Order[]
     */
    public function getOrdersFromParameters(
        ?DateTimeInterface $createdAfter = null,
        ?DateTimeInterface $createdBefore = null,
        ?DateTimeInterface $updatedAfter = null,
        ?DateTimeInterface $updatedBefore = null,
        ?string $status = null,
        int $limit = self::DEFAULT_LIMIT,
        int $offset = self::DEFAULT_OFFSET,
        string $sortBy = self::DEFAULT_SORT_BY,
        string $sortDirection = self::DEFAULT_SORT_DIRECTION
    ): array {
        $parameters = clone $this->parameters;

        $this->setListDimensions($parameters, $limit, $offset);
        $this->setSortParametersList($parameters, $sortBy, $sortDirection);

        if (!empty($createdAfter)) {
            $parameters->set(['CreatedAfter' => $createdAfter->format('Y-m-d\TH:i:s')]);
        }

        if (!empty($createdBefore)) {
            $parameters->set(['CreatedBefore' => $createdBefore->format('Y-m-d\TH:i:s')]);
        }

        if (!empty($updatedAfter)) {
            $parameters->set(['UpdatedAfter' => $updatedAfter->format('Y-m-d\TH:i:s')]);
        }

        if (!empty($updatedBefore)) {
            $parameters->set(['UpdatedBefore' => $updatedBefore->format('Y-m-d\TH:i:s')]);
        }

        if (!empty($status) && in_array($status, OrderStatus::STATUS)) {
            $parameters->set(['Status' => $status]);
        }

        return $this->getOrders($parameters);
    }

    /**
     * @return OrderItem[]
     */
    public function setStatusToPackedByMarketplace(
        array $orderItemIds,
        string $deliveryType,
        string $shippingProvider = null,
        string $trackingNumber = null
    ): array {
        $action = self::SET_STATUS_TO_PACKED_BY_MARKETPLACE_ACTION;

        $parameters = clone $this->parameters;
        $parameters->set([
            'OrderItemIds' => Json::encode($orderItemIds),
            'DeliveryType' => $deliveryType,
        ]);

        if (!empty($shippingProvider)) {
            $parameters->set(['ShippingProvider' => $shippingProvider]);
        }

        if (!empty($trackingNumber)) {
            $parameters->set(['TrackingNumber' => $trackingNumber]);
        }

        $requestId = $this->generateRequestId();

        $builtResponse = $this->executeAction($action, $requestId, $parameters, 'POST');

        $orderItems = OrderItemsFactory::makeFromStatus($builtResponse->getBody());

        $orderItemsResponse = array_values($orderItems->all());

        $this->logger->info(
            sprintf(
                '%d::%s::APIResponse::SellerCenterSdk: the items status was changed',
                $requestId,
                $action
            )
        );

        return $orderItemsResponse;
    }

    /**
     * @return OrderItem[]
     */
    public function setStatusToReadyToShip(
        array $orderItemIds,
        string $deliveryType,
        string $shippingProvider = null,
        string $trackingNumber = null
    ): array {
        $action = self::SET_STATUS_TO_READY_TO_SHIP_ACTION;

        $parameters = clone $this->parameters;
        $parameters->set([
            'OrderItemIds' => Json::encode($orderItemIds),
            'DeliveryType' => $deliveryType,
        ]);

        if (!empty($shippingProvider)) {
            $parameters->set(['ShippingProvider' => $shippingProvider]);
        }

        if (!empty($trackingNumber)) {
            $parameters->set(['TrackingNumber' => $trackingNumber]);
        }

        $requestId = $this->generateRequestId();

        $builtResponse = $this->executeAction($action, $requestId, $parameters, 'POST');

        $orderItems = OrderItemsFactory::makeFromStatus($builtResponse->getBody());

        $orderItemsResponse = array_values($orderItems->all());

        $this->logger->info(
            sprintf(
                '%d::%s::APIResponse::SellerCenterSdk: the items status was changed',
                $requestId,
                $action
            )
        );

        return $orderItemsResponse;
    }

    public function setStatusToCanceled(int $orderItemId, string $reason, string $reasonDetail = null): void
    {
        $action = self::SET_STATUS_TO_CANCELED_ACTION;

        $parameters = clone $this->parameters;
        $parameters->set([
            'OrderItemId' => $orderItemId,
            'Reason' => $reason,
        ]);

        if (!empty($reasonDetail)) {
            $parameters->set(['ReasonDetail' => $reasonDetail]);
        }

        $requestId = $this->generateRequestId();

        $this->executeAction($action, $requestId, $parameters, 'POST');

        $this->logger->info(
            sprintf(
                '%d::%s::APIResponse::SellerCenterSdk: the items status was changed',
                $requestId,
                $action
            )
        );
    }

    /**
     * @return FailureReason[]
     */
    public function getFailureReasons(): array
    {
        $action = self::GET_FAILURES_REASON_ACTION;

        $requestId = $this->generateRequestId();

        $builtResponse = $this->executeAction($action, $requestId);

        $reasons = FailureReasonsFactory::make($builtResponse->getBody());

        $reasonsResponse = $reasons->all();

        $this->logger->info(
            sprintf(
                '%d::%s::APIResponse::SellerCenterSdk: %d failure reasons was recovered',
                $requestId,
                $action,
                count($reasons->all())
            )
        );

        return $reasonsResponse;
    }

    protected function setListDimensions(Parameters &$parameters, int $limit, int $offset): void
    {
        $verifiedLimit = $limit >= 1 ? $limit : self::DEFAULT_LIMIT;
        $verifiedOffset = $offset < 0 ? self::DEFAULT_OFFSET : $offset;

        $parameters->set(
            [
                'Limit' => $verifiedLimit,
                'Offset' => $verifiedOffset,
            ]
        );
    }

    protected function setSortParametersList(Parameters &$parameters, string $sortBy, string $sortDirection): void
    {
        if (!in_array($sortBy, OrderSortFilters::SORT_FILTERS)) {
            $sortBy = self::DEFAULT_SORT_BY;
        }

        if (!in_array($sortDirection, OrderSortDirections::SORT_DIRECTIONS)) {
            $sortDirection = self::DEFAULT_SORT_DIRECTION;
        }

        $parameters->set([
            'SortBy' => $sortBy,
            'SortDirection' => $sortDirection,
        ]);
    }
}
