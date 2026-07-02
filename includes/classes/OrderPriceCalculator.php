<?php

final class OrderPriceCalculator
{
    private const GROUP_DISCOUNT_RATE = 0.10;
    private const GROUP_DISCOUNT_EXTRA_PEOPLE = 5;
    private const DELIVERY_BASE_PRICE = 5.0;
    private const DELIVERY_PRICE_PER_KM = 0.59;

    public function calculate(
        float $unitPrice,
        int $minimumPeople,
        int $people,
        bool $outsideBordeaux,
        float $distanceKm
    ): array {
        $menuPrice = $unitPrice * $people;
        $discount = $this->hasGroupDiscount($minimumPeople, $people)
            ? $menuPrice * self::GROUP_DISCOUNT_RATE
            : 0.0;
        $deliveryPrice = $outsideBordeaux
            ? self::DELIVERY_BASE_PRICE + (self::DELIVERY_PRICE_PER_KM * $distanceKm)
            : 0.0;

        return [
            'menu_price' => round($menuPrice, 2),
            'discount' => round($discount, 2),
            'delivery_price' => round($deliveryPrice, 2),
            'total' => round($menuPrice - $discount + $deliveryPrice, 2),
        ];
    }

    private function hasGroupDiscount(int $minimumPeople, int $people): bool
    {
        return $people >= ($minimumPeople + self::GROUP_DISCOUNT_EXTRA_PEOPLE);
    }
}
