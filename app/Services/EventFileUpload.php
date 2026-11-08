<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventFile;
use Core\Constants\Constants;
use Lib\Validations;

/**
 * Recebe um item de $_FILES e o transforma em Anexo de um Evento.
 *
 * Equivalente do ProfileAvatar do professor, generalizado para a relação 1xN. Uso:
 *
 *     $service = new EventFileUpload($event);
 *     $eventFile = $service->upload($_FILES['file']);   // EventFile salvo, ou null em erro
 *     $message = $service->errors();                    // mensagem em pt-BR, ou null
 *
 * Segurança (nesta ordem, a primeira falha interrompe e nada é gravado):
 * - erro de upload do PHP, tamanho (MAX_SIZE) e extensão do nome enviado;
 * - tipo real do conteúdo por finfo no arquivo temporário, nunca o `type` enviado pelo cliente;
 * - o nome gravado é aleatório e a extensão vem do MIME verificado, nunca do nome enviado;
 * - só um arquivo realmente recebido por HTTP é movido (is_uploaded_file).
 */
class EventFileUpload
{
    /** Tamanho máximo do arquivo, em bytes (2 MB). */
    public const MAX_SIZE = 2 * 1024 * 1024;

    /** Extensões aceitas no nome enviado; o MIME real ainda precisa combinar com ela. */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png'];

    private const ATTRIBUTE = 'file';

    private EventFile $validation;

    public function __construct(private Event $event)
    {
        $this->validation = new EventFile();
    }

    /**
     * @param array<string, mixed> $file item de $_FILES
     * @return EventFile|null Anexo salvo, ou null se algo falhou (veja errors())
     */
    public function upload(array $file): ?EventFile
    {
        $this->validation = new EventFile();

        if ($this->event->newRecord()) {
            $this->fail('o Evento precisa estar salvo antes de receber anexos!');
            return null;
        }

        $mimeType = $this->validate($file);

        if ($mimeType === null) {
            return null;
        }

        return $this->store($file, $mimeType);
    }

    /**
     * Mensagem do último envio (já em pt-BR, sem o nome do campo), ou null se deu certo.
     */
    public function errors(): ?string
    {
        return $this->validation->errors(self::ATTRIBUTE);
    }

    /**
     * Move o arquivo recebido por HTTP para o destino. Protegido para o teste unitário substituir:
     * move_uploaded_file só aceita arquivos de um upload real.
     */
    protected function moveFile(string $source, string $destination): bool
    {
        return is_uploaded_file($source) && move_uploaded_file($source, $destination);
    }

    /**
     * @param array<string, mixed> $file
     * @return string|null o MIME type verificado, ou null se o arquivo foi recusado
     */
    private function validate(array $file): ?string
    {
        $tmpName = $file['tmp_name'] ?? null;

        if (!$this->validateUploadError($file)) {
            return null;
        }

        if (!is_string($tmpName) || $tmpName === '' || !is_file($tmpName) || !is_readable($tmpName)) {
            return $this->fail('não foi possível receber o arquivo, tente novamente!');
        }

        if (
            !Validations::fileMaxSize(self::ATTRIBUTE, $this->validation, $file, self::MAX_SIZE) ||
            !Validations::fileExtension(self::ATTRIBUTE, $this->validation, $file, self::EXTENSIONS)
        ) {
            return null;
        }

        $mimeType = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmpName);

        if (!array_key_exists($mimeType, EventFile::MIME_TYPES)) {
            return $this->fail('não é uma imagem JPEG ou PNG válida!');
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));

        if ($extension !== EventFile::MIME_TYPES[$mimeType] && !($mimeType === 'image/jpeg' && $extension === 'jpeg')) {
            return $this->fail('o conteúdo do arquivo não corresponde à extensão!');
        }

        return $mimeType;
    }

    /**
     * @param array<string, mixed> $file
     */
    private function validateUploadError(array $file): bool
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        $messages = [
            UPLOAD_ERR_INI_SIZE => 'deve ter no máximo 2 MB!',
            UPLOAD_ERR_FORM_SIZE => 'deve ter no máximo 2 MB!',
            UPLOAD_ERR_PARTIAL => 'não foi enviado por completo, tente novamente!',
            UPLOAD_ERR_NO_FILE => 'selecione um arquivo!',
        ];

        if ($error === UPLOAD_ERR_OK) {
            return true;
        }

        $this->fail($messages[$error] ?? 'não foi possível receber o arquivo, tente novamente!');

        return false;
    }

    /**
     * @param array<string, mixed> $file
     */
    private function store(array $file, string $mimeType): ?EventFile
    {
        $publicDir = "/assets/uploads/events/{$this->event->id}/";
        $directory = (string) Constants::rootPath()->join('/public' . $publicDir);
        $storedName = bin2hex(random_bytes(16)) . '.' . EventFile::MIME_TYPES[$mimeType];

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            $this->fail('não foi possível salvar o arquivo, tente novamente!');
            return null;
        }

        if (!$this->moveFile($file['tmp_name'], $directory . $storedName)) {
            $this->fail('não foi possível salvar o arquivo, tente novamente!');
            $this->removeEmptyDirectory($directory);
            return null;
        }

        $eventFile = new EventFile([
            'event_id' => $this->event->id,
            'name' => $this->originalName((string) $file['name']),
            'path' => $publicDir . $storedName,
            'mime_type' => $mimeType,
        ]);

        try {
            $saved = $eventFile->save();
        } catch (\Throwable $exception) {
            $this->discard($directory, $storedName);
            throw $exception;
        }

        if (!$saved) {
            $this->discard($directory, $storedName);
            $this->fail('não foi possível registrar o arquivo!');
            return null;
        }

        return $eventFile;
    }

    /**
     * Nome original só para exibição: sem pasta, sem caracteres de controle, até 255 caracteres.
     */
    private function originalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $name);

        return mb_substr($name, 0, 255);
    }

    private function discard(string $directory, string $storedName): void
    {
        if (is_file($directory . $storedName)) {
            unlink($directory . $storedName);
        }

        $this->removeEmptyDirectory($directory);
    }

    private function removeEmptyDirectory(string $directory): void
    {
        if (is_dir($directory) && count(scandir($directory) ?: []) === 2) {
            rmdir($directory);
        }
    }

    private function fail(string $message): null
    {
        $this->validation->addError(self::ATTRIBUTE, $message);

        return null;
    }
}
