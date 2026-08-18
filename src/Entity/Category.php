<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use App\Trait\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Catégorie de produits pouvant être organisée en hiérarchie parent/enfants.
 */
#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Category
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $name = null;

    #[ORM\Column(length: 4000, nullable: true)]
    #[Assert\Length(
        max: 4000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $description = null;

    /**
     * Produits rattachés à cette catégorie.
     *
     * @var Collection<int, Product>
     */
    #[ORM\ManyToMany(targetEntity: Product::class, mappedBy: 'categories')]
    private Collection $products;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?self $parent = null;

    /**
     * Sous-catégories directement rattachées à cette catégorie.
     *
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $children;

    public function __construct()
    {
        $this->products = new ArrayCollection();
        $this->children = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Retourne les produits rattachés à cette catégorie.
     *
     * @return Collection<int, Product> Collection des produits associés.
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    /**
     * Associe un produit à la catégorie en maintenant la relation inverse.
     *
     * @param Product $product Produit à associer.
     *
     * @return static Instance courante de la catégorie.
     */
    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->addCategory($this);
        }

        return $this;
    }

    /**
     * Retire un produit de la catégorie en maintenant la relation inverse.
     *
     * @param Product $product Produit à retirer.
     *
     * @return static Instance courante de la catégorie.
     */
    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            $product->removeCategory($this);
        }

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Retourne les sous-catégories directes.
     *
     * @return Collection<int, self> Collection des sous-catégories directes.
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * Ajoute une sous-catégorie et renseigne sa catégorie parente.
     *
     * @param self $child Sous-catégorie à ajouter.
     *
     * @return static Instance courante de la catégorie.
     */
    public function addChild(self $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    /**
     * Retire une sous-catégorie et supprime son lien parent si nécessaire.
     *
     * @param self $child Sous-catégorie à retirer.
     *
     * @return static Instance courante de la catégorie.
     */
    public function removeChild(self $child): static
    {
        if ($this->children->removeElement($child) && $child->getParent() === $this) {
            $child->setParent(null);
        }

        return $this;
    }
}
