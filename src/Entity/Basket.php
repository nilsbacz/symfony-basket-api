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
        // If the same product already exists in this basket, increase its quantity
        foreach ($this->items as $existing) {
            if ($existing->getProduct() === $item->getProduct()) {
                $existing->setQuantity($existing->getQuantity() + $item->getQuantity());
                return $existing;
            }
        }

        // Otherwise add as a new line
        $this->items->add($item);
        $item->setBasket($this);
        return $item;
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
