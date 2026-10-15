<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1NF : les listes « documents_requis » et « regles_specifiques » rangées en JSON dans
 * type_demandes deviennent deux tables filles (une ligne par pièce, une ligne par règle).
 * Chaque pièce jointe est désormais reliée par clé étrangère à la pièce requise qu'elle fournit ;
 * type_document reste le libellé figé au moment du dépôt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents_requis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_demande_id')->constrained('type_demandes')->cascadeOnDelete();
            $table->string('libelle', 150);
            $table->timestamps();

            $table->unique(['type_demande_id', 'libelle']);
        });

        Schema::create('regles_specifiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_demande_id')->constrained('type_demandes')->cascadeOnDelete();
            $table->string('libelle', 255);
            $table->timestamps();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('document_requis_id')->nullable()->after('demande_id')
                ->constrained('documents_requis')->nullOnDelete();
        });

        $maintenant = now();

        foreach (DB::table('type_demandes')->orderBy('id')->get() as $type) {
            foreach (self::liste($type->documents_requis) as $libelle) {
                DB::table('documents_requis')->insert([
                    'type_demande_id' => $type->id, 'libelle' => mb_substr($libelle, 0, 150),
                    'created_at' => $maintenant, 'updated_at' => $maintenant,
                ]);
            }
            foreach (self::liste($type->regles_specifiques) as $libelle) {
                DB::table('regles_specifiques')->insert([
                    'type_demande_id' => $type->id, 'libelle' => mb_substr($libelle, 0, 255),
                    'created_at' => $maintenant, 'updated_at' => $maintenant,
                ]);
            }
        }

        // Relie chaque pièce déjà déposée à la pièce requise de même libellé pour le type de sa demande.
        $correspondances = DB::table('documents')
            ->join('demandes', 'demandes.id', '=', 'documents.demande_id')
            ->join('documents_requis', function ($join) {
                $join->on('documents_requis.type_demande_id', '=', 'demandes.type_demande_id')
                    ->on('documents_requis.libelle', '=', 'documents.type_document');
            })
            ->select('documents.id as document_id', 'documents_requis.id as document_requis_id')
            ->pluck('document_requis_id', 'document_id');

        foreach ($correspondances as $documentId => $documentRequisId) {
            DB::table('documents')->where('id', $documentId)->update(['document_requis_id' => $documentRequisId]);
        }

        Schema::table('type_demandes', function (Blueprint $table) {
            $table->dropColumn(['documents_requis', 'regles_specifiques']);
        });
    }

    public function down(): void
    {
        Schema::table('type_demandes', function (Blueprint $table) {
            $table->json('documents_requis')->nullable();
            $table->json('regles_specifiques')->nullable();
        });

        foreach (DB::table('type_demandes')->pluck('id') as $typeId) {
            DB::table('type_demandes')->where('id', $typeId)->update([
                'documents_requis' => json_encode(DB::table('documents_requis')->where('type_demande_id', $typeId)->orderBy('id')->pluck('libelle')->all(), JSON_UNESCAPED_UNICODE),
                'regles_specifiques' => json_encode(DB::table('regles_specifiques')->where('type_demande_id', $typeId)->orderBy('id')->pluck('libelle')->all(), JSON_UNESCAPED_UNICODE),
            ]);
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_requis_id');
        });

        Schema::dropIfExists('regles_specifiques');
        Schema::dropIfExists('documents_requis');
    }

    /**
     * Libellés d'une ancienne colonne JSON, sans doublon. Une paire clé/valeur ({"max_personnes": 10})
     * devient le texte « max_personnes : 10 ».
     *
     * @return array<int, string>
     */
    private static function liste(?string $json): array
    {
        $valeurs = json_decode($json ?? '[]', true);
        if (! is_array($valeurs)) {
            return [];
        }

        $libelles = [];
        foreach ($valeurs as $cle => $valeur) {
            $texte = is_scalar($valeur) ? (string) $valeur : json_encode($valeur, JSON_UNESCAPED_UNICODE);
            $libelles[] = trim(is_string($cle) ? "$cle : $texte" : $texte);
        }

        return array_values(array_unique(array_filter($libelles, fn ($l) => $l !== '')));
    }
};
