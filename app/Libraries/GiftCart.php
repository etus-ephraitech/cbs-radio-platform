<?php

namespace App\Libraries;

/**
 * The visitor's gift cart: gift id => quantity, kept in their session. It holds only what the visitor chose; the
 * price, the stock and the inventory are looked up again at checkout, so a stale cart can never buy at an old
 * price. One line per gift (adding the same gift again adds to its quantity).
 */
class GiftCart
{
    public const MAX_LINES    = 20;  // the most lines the vendor accepts in one checkout
    public const MAX_QUANTITY = 100; // and the most of one line

    private const SESSION_KEY  = 'gift_cart';
    private const PENDING_KEY  = 'gift_cart_checkout';

    /**
     * @return array<int, int> gift id => quantity
     */
    public function lines(): array
    {
        $stored = session()->get(self::SESSION_KEY);
        $lines  = [];

        foreach (is_array($stored) ? $stored : [] as $giftId => $quantity) {
            if ((int) $giftId > 0 && (int) $quantity > 0) {
                $lines[(int) $giftId] = min((int) $quantity, self::MAX_QUANTITY);
            }
        }

        return $lines;
    }

    public function isEmpty(): bool
    {
        return $this->lines() === [];
    }

    /**
     * How many gifts in all, for the number beside the cart link.
     */
    public function count(): int
    {
        return array_sum($this->lines());
    }

    /**
     * Adds to a gift's quantity. Returns false when the cart is full of other gifts. A quantity that would pass
     * $limit (the most there is to buy) or the per-line maximum is cut down to it.
     */
    public function add(int $giftId, int $quantity, ?int $limit = null): bool
    {
        $lines = $this->lines();

        if (! isset($lines[$giftId]) && count($lines) >= self::MAX_LINES) {
            return false;
        }

        $this->store($lines, $giftId, ($lines[$giftId] ?? 0) + max($quantity, 0), $limit);

        return true;
    }

    /**
     * Sets a gift's quantity; zero (or less) removes the line.
     */
    public function set(int $giftId, int $quantity, ?int $limit = null): void
    {
        $this->store($this->lines(), $giftId, $quantity, $limit);
    }

    public function remove(int $giftId): void
    {
        $this->set($giftId, 0);
    }

    public function clear(): void
    {
        session()->remove([self::SESSION_KEY, self::PENDING_KEY]);
    }

    /**
     * Remembers which purchase the cart was sent as, so that the cart can be emptied once that purchase is paid
     * (and not before: a visitor who changes their mind on the vendor's page still has their cart).
     */
    public function sentAs(string $publicToken): void
    {
        session()->set(self::PENDING_KEY, $publicToken);
    }

    public function wasSentAs(string $publicToken): bool
    {
        return session()->get(self::PENDING_KEY) === $publicToken;
    }

    /**
     * @param array<int, int> $lines
     */
    private function store(array $lines, int $giftId, int $quantity, ?int $limit): void
    {
        $quantity = min($quantity, self::MAX_QUANTITY, $limit ?? self::MAX_QUANTITY);

        if ($quantity > 0) {
            $lines[$giftId] = $quantity;
        } else {
            unset($lines[$giftId]);
        }

        session()->set(self::SESSION_KEY, $lines);
    }
}
