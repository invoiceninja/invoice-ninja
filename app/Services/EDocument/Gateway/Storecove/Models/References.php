<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

class References
{
    public ?string $document_type;
    public ?string $document_type_code;
    public ?string $document_id;
    public ?string $document_uuid;
    public ?string $document_id_scheme_id;
    public ?string $document_id_scheme_agency_id;
    public ?string $document_id_scheme_version_id;
    public ?string $document_id_list_id;
    public ?string $document_id_list_agency_id;
    public ?string $document_id_list_version_id;
    public ?string $line_id;
    public ?string $issue_date;
    public ?string $document_description;

    public function __construct(
        ?string $document_type,
        ?string $document_type_code,
        ?string $document_id,
        ?string $document_uuid,
        ?string $document_id_scheme_id,
        ?string $document_id_scheme_agency_id,
        ?string $document_id_scheme_version_id,
        ?string $document_id_list_id,
        ?string $document_id_list_agency_id,
        ?string $document_id_list_version_id,
        ?string $line_id,
        ?string $issue_date,
        ?string $document_description
    ) {
        $this->document_type = $document_type;
        $this->document_type_code = $document_type_code;
        $this->document_id = $document_id;
        $this->document_uuid = $document_uuid;
        $this->document_id_scheme_id = $document_id_scheme_id;
        $this->document_id_scheme_agency_id = $document_id_scheme_agency_id;
        $this->document_id_scheme_version_id = $document_id_scheme_version_id;
        $this->document_id_list_id = $document_id_list_id;
        $this->document_id_list_agency_id = $document_id_list_agency_id;
        $this->document_id_list_version_id = $document_id_list_version_id;
        $this->line_id = $line_id;
        $this->issue_date = $issue_date;
        $this->document_description = $document_description;
    }

    public function getDocumentType(): ?string
    {
        return $this->document_type;
    }

    public function getDocumentTypeCode(): ?string
    {
        return $this->document_type_code;
    }

    public function getDocumentId(): ?string
    {
        return $this->document_id;
    }

    public function getDocumentUuid(): ?string
    {
        return $this->document_uuid;
    }

    public function getDocumentIdSchemeId(): ?string
    {
        return $this->document_id_scheme_id;
    }

    public function getDocumentIdSchemeAgencyId(): ?string
    {
        return $this->document_id_scheme_agency_id;
    }

    public function getDocumentIdSchemeVersionId(): ?string
    {
        return $this->document_id_scheme_version_id;
    }

    public function getDocumentIdListId(): ?string
    {
        return $this->document_id_list_id;
    }

    public function getDocumentIdListAgencyId(): ?string
    {
        return $this->document_id_list_agency_id;
    }

    public function getDocumentIdListVersionId(): ?string
    {
        return $this->document_id_list_version_id;
    }

    public function getLineId(): ?string
    {
        return $this->line_id;
    }

    public function getIssueDate(): ?string
    {
        return $this->issue_date;
    }

    public function getDocumentDescription(): ?string
    {
        return $this->document_description;
    }

    public function setDocumentType(?string $document_type): self
    {
        $this->document_type = $document_type;
        return $this;
    }

    public function setDocumentTypeCode(?string $document_type_code): self
    {
        $this->document_type_code = $document_type_code;
        return $this;
    }

    public function setDocumentId(?string $document_id): self
    {
        $this->document_id = $document_id;
        return $this;
    }

    public function setDocumentUuid(?string $document_uuid): self
    {
        $this->document_uuid = $document_uuid;
        return $this;
    }

    public function setDocumentIdSchemeId(?string $document_id_scheme_id): self
    {
        $this->document_id_scheme_id = $document_id_scheme_id;
        return $this;
    }

    public function setDocumentIdSchemeAgencyId(?string $document_id_scheme_agency_id): self
    {
        $this->document_id_scheme_agency_id = $document_id_scheme_agency_id;
        return $this;
    }

    public function setDocumentIdSchemeVersionId(?string $document_id_scheme_version_id): self
    {
        $this->document_id_scheme_version_id = $document_id_scheme_version_id;
        return $this;
    }

    public function setDocumentIdListId(?string $document_id_list_id): self
    {
        $this->document_id_list_id = $document_id_list_id;
        return $this;
    }

    public function setDocumentIdListAgencyId(?string $document_id_list_agency_id): self
    {
        $this->document_id_list_agency_id = $document_id_list_agency_id;
        return $this;
    }

    public function setDocumentIdListVersionId(?string $document_id_list_version_id): self
    {
        $this->document_id_list_version_id = $document_id_list_version_id;
        return $this;
    }

    public function setLineId(?string $line_id): self
    {
        $this->line_id = $line_id;
        return $this;
    }

    public function setIssueDate(?string $issue_date): self
    {
        $this->issue_date = $issue_date;
        return $this;
    }

    public function setDocumentDescription(?string $document_description): self
    {
        $this->document_description = $document_description;
        return $this;
    }
}
