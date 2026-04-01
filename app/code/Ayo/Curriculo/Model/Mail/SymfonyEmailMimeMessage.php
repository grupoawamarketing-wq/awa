<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Model\Mail;

use Magento\Framework\Mail\MimeInterface;
use Magento\Framework\Mail\MimeMessageInterface;
use Magento\Framework\Mail\MimePartInterface;
use Symfony\Component\Mime\Email;

/**
 * Wrapper para usar \Symfony\Component\Mime\Email como body do \Magento\Framework\Mail\EmailMessage.
 *
 * Magento 2.4.x expõe envio via Symfony Mailer. Este wrapper permite anexos (attachments)
 * sem depender do TransportBuilder padrão (que monta apenas uma parte HTML/TEXT).
 */
final class SymfonyEmailMimeMessage implements MimeMessageInterface
{
    public function __construct(
        private readonly Email $email
    ) {
    }

    /**
     * Compatível com o uso interno do Magento\Framework\Mail\EmailMessage.
     */
    public function getMimeMessage(): \Symfony\Component\Mime\Message
    {
        return $this->email;
    }

    /**
     * @return MimePartInterface[]
     */
    public function getParts(): array
    {
        // Não é usado no fluxo de envio (Transport) que trabalha com Symfony Message.
        return [];
    }

    public function isMultiPart(): bool
    {
        $body = $this->email->getBody();
        return is_object($body) && method_exists($body, 'countParts') && $body->countParts() > 0;
    }

    public function getMessage(string $endOfLine = MimeInterface::LINE_END): string
    {
        return str_replace("\r\n", $endOfLine, $this->email->toString());
    }

    public function getPartHeadersAsArray(int $partNum): array
    {
        return [];
    }

    public function getPartHeaders(int $partNum, string $endOfLine = MimeInterface::LINE_END): string
    {
        return '';
    }

    public function getPartContent(int $partNum, string $endOfLine = MimeInterface::LINE_END): string
    {
        return '';
    }
}
