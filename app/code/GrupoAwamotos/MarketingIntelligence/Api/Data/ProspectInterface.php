<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Api\Data;

/**
 * @api
 */
interface ProspectInterface
{
    public const PROSPECT_ID = 'prospect_id';
    public const CNPJ = 'cnpj';
    public const RAZAO_SOCIAL = 'razao_social';
    public const NOME_FANTASIA = 'nome_fantasia';
    public const CNAE_PRINCIPAL = 'cnae_principal';
    public const CNAE_DESCRICAO = 'cnae_descricao';
    public const CNAE_PROFILE = 'cnae_profile';
    public const UF = 'uf';
    public const MUNICIPIO = 'municipio';
    public const CEP = 'cep';
    public const EMAIL = 'email';
    public const TELEFONE = 'telefone';
    public const CAPITAL_SOCIAL = 'capital_social';
    public const PORTE = 'porte';
    public const DATA_ABERTURA = 'data_abertura';
    public const SITUACAO_CADASTRAL = 'situacao_cadastral';
    public const PROSPECT_SCORE = 'prospect_score';
    public const PROSPECT_STATUS = 'prospect_status';
    public const SOURCE = 'source';
    public const NOTES = 'notes';
    public const CONTACTED_AT = 'contacted_at';
    public const CONVERTED_CUSTOMER_ID = 'converted_customer_id';
    public const FETCHED_AT = 'fetched_at';
    public const UPDATED_AT = 'updated_at';

    public function getProspectId(): ?int;

    public function setProspectId(int $prospectId): self;

    public function getCnpj(): ?string;

    public function setCnpj(string $cnpj): self;

    public function getRazaoSocial(): ?string;

    public function setRazaoSocial(string $razaoSocial): self;

    public function getNomeFantasia(): ?string;

    public function setNomeFantasia(?string $nomeFantasia): self;

    public function getCnaePrincipal(): ?string;

    public function setCnaePrincipal(?string $cnaePrincipal): self;

    public function getCnaeDescricao(): ?string;

    public function setCnaeDescricao(?string $cnaeDescricao): self;

    public function getCnaeProfile(): ?string;

    public function setCnaeProfile(?string $cnaeProfile): self;

    public function getUf(): ?string;

    public function setUf(?string $uf): self;

    public function getMunicipio(): ?string;

    public function setMunicipio(?string $municipio): self;

    public function getCep(): ?string;

    public function setCep(?string $cep): self;

    public function getEmail(): ?string;

    public function setEmail(?string $email): self;

    public function getTelefone(): ?string;

    public function setTelefone(?string $telefone): self;

    public function getCapitalSocial(): ?float;

    public function setCapitalSocial(?float $capitalSocial): self;

    public function getPorte(): ?string;

    public function setPorte(?string $porte): self;

    public function getDataAbertura(): ?string;

    public function setDataAbertura(?string $dataAbertura): self;

    public function getSituacaoCadastral(): ?string;

    public function setSituacaoCadastral(?string $situacaoCadastral): self;

    public function getProspectScore(): ?int;

    public function setProspectScore(?int $prospectScore): self;

    public function getProspectStatus(): ?string;

    public function setProspectStatus(string $prospectStatus): self;

    public function getSource(): ?string;

    public function setSource(?string $source): self;

    public function getNotes(): ?string;

    public function setNotes(?string $notes): self;

    public function getContactedAt(): ?string;

    public function setContactedAt(?string $contactedAt): self;

    public function getConvertedCustomerId(): ?int;

    public function setConvertedCustomerId(?int $customerId): self;

    public function getFetchedAt(): ?string;

    public function setFetchedAt(?string $fetchedAt): self;

    public function getUpdatedAt(): ?string;

    public function setUpdatedAt(?string $updatedAt): self;
}
