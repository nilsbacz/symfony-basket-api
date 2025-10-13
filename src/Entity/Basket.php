<?php

namespace App\Entity;

use App\Repository\BasketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BasketRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Basket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, BasketItem>
     */
    #[ORM\OneToMany(targetEntity: BasketItem::class, mappedBy: 'basket')]
    private Collection $items;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, BasketItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(BasketItem $item): BasketItem
    {
        $product = $item->getProduct();
        $qty     = $item->getQuantity();

        $existing = $this->findItemByProduct($product);

        // Adjust inventory once, in one place (with guard)
        $this->decreaseStock($product, $qty);

        if ($existing) {
            $existing->setQuantity($existing->getQuantity() + $qty);
            return $existing;
        }

        // New line item
        $item->setBasket($this);
        $this->items->add($item);
        return $item;
    }

    private function findItemByProduct(Product $product): ?BasketItem
    {
        foreach ($this->items as $existing) {
            if ($existing->getProduct() === $product) {
                return $existing;
            }
        }
        return null;
    }

    private function decreaseStock(Product $product, int $by): void
    {
        $newQty = $product->getQuantity() - $by;
        if ($newQty < 0) {
            throw new \DomainException('product out of stock. ID: '.$product->getId());
        }
        $product->setQuantity($newQty);
    }


    public function removeItem(BasketItem $item): static
    {
        if ($this->items->removeElement($item)) {
            // set the owning side to null (unless already changed)
            if ($item->getBasket() === $this) {
                $item->setBasket(null);
            }
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'createdAt' => $this->getCreatedAt()?->format(DATE_ATOM),
            'items' => array_map(
                fn($item) => $item->toArray(),
                $this->getItems()->toArray()
            ),
        ];
    }
}
