<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model;

use GrupoAwamotos\MarketingIntelligence\Api\Data\ProspectInterface;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Prospect as ProspectResource;
use Magento\Framework\Model\AbstractModel;

class Prospect extends AbstractModel implements ProspectInterface
{
    protected $_eventPrefix = 'grupoawamotos_mktg_prospect';

    protected function _construct(): void
    {
        $this->_init(ProspectResource::class);
    }

    public function getProspectId(): ?int
    {
        $value = $this->getData(self::PROSPECT_ID);
        return $value !== null ? (int) $value : null;
    }

    public function setProspectId(int $prospectId): self
    {
        return $this->setData(self::PROSPECT_ID, $prospectId);
    }

    public function getCnpj(): ?string
    {
        return $this->getData(self::CNPJ);
    }

    public function setCnpj(string $cnpj): self
    {
        return $this->setData(self::CNPJ, $cnpj);
    }

    public function getRazaoSocial(): ?string
    {
        return $this->getData(self::RAZAO_SOCIAL);
    }

    public function setRazaoSocial(string $razaoSocial): self
    {
        return $this->setData(self::RAZAO_SOCIAL, $razaoSocial);
    }

    public function getNomeFantasia(): ?string
    {
        return $this->getData(self::NOME_FANTASIA);
    }

    public function setNomeFantasia(?string $nomeFantasia): self
    {
        return $this->setData(self::NOME_FANTASIA, $nomeFantasia);
    }

    public function getCnaePrincipal(): ?string
    {
        return $this->getData(self::CNAE_PRINCIPAL);
    }

    public function setCnaePrincipal(?string $cnaePrincipal): self
    {
        return $this->setData(self::CNAE_PRINCIPAL, $cnaePrincipal);
    }

    public function getCnaeDescricao(): ?string
    {
        return $this->getData(self::CNAE_DESCRICAO);
    }

    public function setCnaeDescricao(?string $cnaeDescricao): self
    {
        return $this->setData(self::CNAE_DESCRICAO, $cnaeDescricao);
    }

    public function getCnaeProfile(): ?string
    {
        return $this->getData(self::CNAE_PROFILE);
    }

    public function setCnaeProfile(?string $cnaeProfile): self
    {
        return $this->setData(self::CNAE_PROFILE, $cnaeProfile);
    }

    public function getUf(): ?string
    {
        return $this->getData(self::UF);
    }

    public function setUf(?string $uf): self
    {
        return $this->setData(self::UF, $uf);
    }

    public function getMunicipio(): ?string
    {
        return $this->getData(self::MUNICIPIO);
    }

    public function setMunicipio(?string $municipio): self
    {
        return $this->setData(self::MUNICIPIO, $municipio);
    }

    public function getCep(): ?string
    {
        return $this->getData(self::CEP);
    }

    public function setCep(?string $cep): self
    {
        return $this->setData(self::CEP, $cep);
    }

    public function getEmail(): ?string
    {
        return $this->getData(self::EMAIL);
    }

    public function setEmail(?string $email): self
    {
        return $this->setData(self::EMAIL, $email);
    }

    public function getTelefone(): ?string
    {
        return $this->getData(self::TELEFONE);
    }

    public function setTelefone(?string $telefone): self
    {
        return $this->setData(self::TELEFONE, $telefone);
    }

    public function getCapitalSocial(): ?float
    {
        $value = $this->getData(self::CAPITAL_SOCIAL);
        return $value !== null ? (float) $value : null;
    }

    public function setCapitalSocial(?float $capitalSocial): self
    {
        return $this->setData(self::CAPITAL_SOCIAL, $capitalSocial);
    }

    public function getPorte(): ?string
    {
        return $this->getData(self::PORTE);
    }

    public function setPorte(?string $porte): self
    {
        return $this->setData(self::PORTE, $porte);
    }

    public function getDataAbertura(): ?string
    {
        return $this->getData(self::DATA_ABERTURA);
    }

    public function setDataAbertura(?string $dataAbertura): self
    {
        return $this->setData(self::DATA_ABERTURA, $dataAbertura);
    }

    public function getSituacaoCadastral(): ?string
    {
        return $this->getData(self::SITUACAO_CADASTRAL);
    }

    public function setSituacaoCadastral(?string $situacaoCadastral): self
    {
        return $this->setData(self::SITUACAO_CADASTRAL, $situacaoCadastral);
    }

    public function getProspectScore(): ?int
    {
        $value = $this->getData(self::PROSPECT_SCORE);
        return $value !== null ? (int) $value : null;
    }

    public function setProspectScore(?int $prospectScore): self
    {
        return $this->setData(self::PROSPECT_SCORE, $prospectScore);
    }

    public function getProspectStatus(): ?string
    {
        return $this->getData(self::PROSPECT_STATUS);
    }

    public function setProspectStatus(string $prospectStatus): self
    {
        return $this->setData(self::PROSPECT_STATUS, $prospectStatus);
    }

    public function getSource(): ?string
    {
        return $this->getData(self::SOURCE);
    }

    public function setSource(?string $source): self
    {
        return $this->setData(self::SOURCE, $source);
    }

    public function getNotes(): ?string
    {
        return $this->getData(self::NOTES);
    }

    public function setNotes(?string $notes): self
    {
        return $this->setData(self::NOTES, $notes);
    }

    public function getContactedAt(): ?string
    {
        return $this->getData(self::CONTACTED_AT);
    }

    public function setContactedAt(?string $contactedAt): self
    {
        return $this->setData(self::CONTACTED_AT, $contactedAt);
    }

    public function getConvertedCustomerId(): ?int
    {
        $value = $this->getData(self::CONVERTED_CUSTOMER_ID);
        return $value !== null ? (int) $value : null;
    }

    public function setConvertedCustomerId(?int $customerId): self
    {
        return $this->setData(self::CONVERTED_CUSTOMER_ID, $customerId);
    }

    public function getFetchedAt(): ?string
    {
        return $this->getData(self::FETCHED_AT);
    }

    public function setFetchedAt(?string $fetchedAt): self
    {
        return $this->setData(self::FETCHED_AT, $fetchedAt);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt(?string $updatedAt): self
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
