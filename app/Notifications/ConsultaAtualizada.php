<?php

namespace App\Notifications;

use App\Models\Consulta;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de que uma consulta mudou (issue #74, D36): confirmada, rejeitada, cancelada,
 * reagendada ou promovida da lista de espera.
 *
 * Vai para a base de dados (para se ver na aplicação) e por email. Não usa filas de propósito:
 * na demonstração ninguém tem um "queue:work" a correr, e um aviso em fila nunca chegava.
 * Um paciente sem conta só recebe o email, no email de contacto da ficha.
 */
class ConsultaAtualizada extends Notification
{
    public const CONFIRMADA = 'confirmada';

    public const REJEITADA = 'rejeitada';

    public const CANCELADA = 'cancelada';

    public const REAGENDADA = 'reagendada';

    public const PROMOVIDA = 'promovida';

    /**
     * @param  self::*  $evento
     * @param  bool  $paraProfissional  o texto muda conforme quem o lê: "a sua consulta" ou "a consulta de :paciente"
     */
    public function __construct(
        public readonly Consulta $consulta,
        public readonly string $evento,
        public readonly ?string $motivo = null,
        public readonly bool $paraProfissional = false,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['database', 'mail'];
    }

    /**
     * Guarda os dados e não o texto: a página de notificações escreve a frase no idioma de quem a lê.
     *
     * @return array{evento: string, consulta_id: int, inicio: string, paciente: string, profissional: string, especialidade: string, motivo: ?string, para_profissional: bool}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'evento' => $this->evento,
            'consulta_id' => $this->consulta->id,
            'inicio' => $this->consulta->inicio->format('Y-m-d H:i'),
            'paciente' => $this->consulta->paciente->nome,
            'profissional' => $this->consulta->profissional->user->name,
            'especialidade' => $this->consulta->especialidade->nome,
            'motivo' => $this->motivo,
            'para_profissional' => $this->paraProfissional,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dados = $this->toArray($notifiable);

        $mensagem = (new MailMessage)
            ->subject(self::titulo($dados).' — '.config('app.name'))
            ->greeting(__('Olá!'))
            ->line(self::texto($dados));

        if ($this->motivo !== null) {
            $mensagem->line(__('Motivo').': '.$this->motivo);
        }

        return $mensagem->action(__('Abrir a aplicação'), route('home'));
    }

    /** @param  array<string, mixed>  $dados  o que toArray() guardou */
    public static function titulo(array $dados): string
    {
        return match ($dados['evento']) {
            self::CONFIRMADA => __('Consulta confirmada'),
            self::REJEITADA => __('Pedido de consulta rejeitado'),
            self::CANCELADA => __('Consulta cancelada'),
            self::REAGENDADA => __('Consulta reagendada'),
            self::PROMOVIDA => __('Saiu da lista de espera'),
        };
    }

    /** @param  array<string, mixed>  $dados  o que toArray() guardou */
    public static function texto(array $dados): string
    {
        $valores = [
            'data' => date('d/m/Y H:i', strtotime($dados['inicio'])),
            'profissional' => $dados['profissional'],
            'paciente' => $dados['paciente'],
        ];

        if ($dados['para_profissional'] ?? false) {
            return match ($dados['evento']) {
                self::REAGENDADA => __('A consulta de :paciente em :data foi reagendada e já não está na sua agenda.', $valores),
                default => __('A consulta de :paciente em :data foi cancelada.', $valores),
            };
        }

        return match ($dados['evento']) {
            self::CONFIRMADA => __('A sua consulta de :data com :profissional está confirmada.', $valores),
            self::REJEITADA => __('O seu pedido de consulta para :data com :profissional foi rejeitado.', $valores),
            self::CANCELADA => __('A sua consulta de :data com :profissional foi cancelada.', $valores),
            self::REAGENDADA => __('A sua consulta foi reagendada para :data com :profissional.', $valores),
            self::PROMOVIDA => __('Ficou livre a vaga de :data com :profissional. O seu pedido saiu da lista de espera e aguarda confirmação da receção.', $valores),
        };
    }
}
