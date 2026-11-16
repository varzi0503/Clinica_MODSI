<?php

namespace App\Http\Controllers;

use App\Models\Consulta;
use App\Models\Documento;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documentos de uma consulta (issue #76, D37). Os ficheiros estão no disco privado e só saem
 * por aqui, depois de a policy confirmar que quem pede é o paciente ou o profissional da consulta.
 */
class DocumentoController extends Controller
{
    public function store(Request $request, Consulta $consulta): RedirectResponse
    {
        $request->validate([
            'ficheiro' => ['required', 'file', 'mimes:'.implode(',', Documento::TIPOS), 'max:'.Documento::TAMANHO_MAXIMO_KB],
        ], [
            // O PHP recusa o ficheiro antes de a aplicação o ver quando passa o upload_max_filesize do php.ini.
            'ficheiro.uploaded' => __('O carregamento falhou: o ficheiro é maior do que o servidor aceita.'),
        ]);

        if (! $consulta->aceitaDocumentos()) {
            return back()->with('error', __('Já não é possível juntar documentos a esta consulta.'));
        }

        $ficheiro = $request->file('ficheiro');
        // O nome no disco é gerado; o nome original fica só na base de dados, para mostrar e descarregar.
        $caminho = $ficheiro->store("documentos/{$consulta->id}", Documento::DISCO);

        $consulta->documentos()->create([
            'user_id' => $request->user()->id,
            'nome_original' => mb_substr($ficheiro->getClientOriginalName(), 0, 255),
            'caminho' => $caminho,
            'mime' => $ficheiro->getMimeType() ?? 'application/octet-stream',
            'tamanho' => $ficheiro->getSize(),
        ]);

        return back()->with('success', __('Documento carregado.'));
    }

    public function show(Documento $documento): StreamedResponse
    {
        $disco = $this->disco();
        abort_unless($disco->exists($documento->caminho), 404);

        return $disco->download($documento->caminho, $documento->nome_original);
    }

    public function destroy(Documento $documento): RedirectResponse
    {
        $documento->delete();
        $this->disco()->delete($documento->caminho);

        return back()->with('success', __('Documento removido.'));
    }

    /** O disco privado dos documentos. O tipo concreto dá acesso a download(), que o contrato não declara. */
    private function disco(): FilesystemAdapter
    {
        return Storage::disk(Documento::DISCO);
    }
}
