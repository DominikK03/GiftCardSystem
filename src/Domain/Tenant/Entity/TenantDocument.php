<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Entity;

use App\Domain\Tenant\Enum\DocumentType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tenant_documents')]
#[ORM\Index(columns: ['tenant_id'], name: 'idx_document_tenant_id')]
#[ORM\Index(columns: ['type'], name: 'idx_document_type')]
class TenantDocument
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36)]
    private string $tenantId;

    #[ORM\Column(type: 'string', enumType: DocumentType::class)]
    private DocumentType $type;

    #[ORM\Column(type: 'string', length: 255)]
    private string $filename;

    #[ORM\Column(type: 'string', length: 255)]
    private string $originalName;

    #[ORM\Column(type: 'string', length: 50)]
    private string $mimeType;

    #[ORM\Column(type: 'integer')]
    private int $size;

    #[ORM\Column(type: 'string', length: 500)]
    private string $storagePath;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        string $id,
        string $tenantId,
        DocumentType $type,
        string $filename,
        string $originalName,
        string $mimeType,
        int $size,
        string $storagePath,
        ?array $metadata
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->type = $type;
        $this->filename = $filename;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->storagePath = $storagePath;
        $this->metadata = $metadata;
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function createAgreement(
        string $id,
        string $tenantId,
        string $filename,
        string $originalName,
        int $size,
        string $storagePath
    ): self {
        return new self(
            $id,
            $tenantId,
            DocumentType::COOPERATION_AGREEMENT,
            $filename,
            $originalName,
            'application/pdf',
            $size,
            $storagePath,
            null
        );
    }

    public static function createSignedAgreement(
        string $id,
        string $tenantId,
        string $filename,
        string $originalName,
        int $size,
        string $storagePath
    ): self {
        return new self(
            $id,
            $tenantId,
            DocumentType::SIGNED_COOPERATION_AGREEMENT,
            $filename,
            $originalName,
            'application/pdf',
            $size,
            $storagePath,
            null
        );
    }

    public static function createInvoice(
        string $id,
        string $tenantId,
        string $filename,
        string $originalName,
        int $size,
        string $storagePath,
        array $metadata
    ): self {
        return new self(
            $id,
            $tenantId,
            DocumentType::INVOICE,
            $filename,
            $originalName,
            'application/pdf',
            $size,
            $storagePath,
            $metadata
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getType(): DocumentType
    {
        return $this->type;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
