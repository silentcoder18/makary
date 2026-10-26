@php
    $statut = $projet['statut'];
    $ouvert = in_array($statut['code'], ['financement_recherche', 'pret_a_etre_soumis'], true);
    $pieces = ['etude_faisabilite' => 'Étude de faisabilité', 'etude_impact' => 'Étude d’impact', 'autorisation' => 'Autorisation', 'permis' => 'Permis',
        'convention' => 'Convention', 'ordre_service' => 'Ordre de service', 'pv_reception' => 'Procès-verbal de réception',
        'attestation_achevement' => 'Attestation d’achèvement', 'rapport' => 'Rapport', 'autre' => 'Pièce'];
@endphp
<x-portail.gabarit :titre="$projet['titre']" :description="$projet['resume'] ?? $projet['titre']">
    <x-portail.entete-page :titre="$projet['titre']" :surtitre="$projet->get('secteur.libelle')" :chapeau="$projet['resume']" :sous-navigation="[]">
        <div class="mt-6 flex flex-wrap items-center gap-3 text-sm">
            <x-portail.badge-statut :statut="$statut" />
            <span class="text-ardoise">Statut mis à jour le {{ $format->date($statut['mis_a_jour_le']) }} · fiche {{ $projet['code'] }} · version {{ $projet->version }}</span>
        </div>
        @if ($format->estDatee($statut['mis_a_jour_le']))
            <p class="mt-4 inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-semibold"><x-icone nom="info" class="icone size-5 text-terre" /> Information datée du {{ $format->date($statut['mis_a_jour_le']) }}</p>
        @endif
        @if ($statut['motif_suspension'])
            <p class="mt-4 rounded-lg bg-white p-3"><strong>Motif de la suspension :</strong> {{ $statut['motif_suspension'] }}</p>
        @endif
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('projets.pdf', $projet['slug']) }}" class="bouton bouton-principal"><x-icone nom="telechargement" class="icone size-5" /> Fiche de financement (PDF)</a>
            @if ($ouvert)
                <a href="{{ route('partenaires.devenir', ['projet' => $projet['slug']]) }}" class="bouton bouton-secondaire">Soutenir ce projet</a>
            @endif
        </div>
    </x-portail.entete-page>

    <div class="conteneur grid gap-12 py-12 lg:grid-cols-[1fr_24rem] lg:py-16">
        <div class="min-w-0 space-y-14">
            @if ($projet['medias'])
                <section aria-labelledby="titre-photos">
                    <h2 id="titre-photos" class="sr-only">Photographies</h2>
                    <ul class="grid gap-4 sm:grid-cols-2">
                        @foreach ($projet['medias'] as $i => $media)
                            <li><figure>
                                <x-portail.vignette :media="$media" class="rounded-carte" :prioritaire="$i === 0" />
                                <figcaption class="mt-2 text-sm text-ardoise">{{ $media['titre'] }} — {{ $media['auteur'] }}{{ $media['date_prise'] ? ', '.$format->date($media['date_prise']) : '' }}</figcaption>
                            </figure></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section aria-labelledby="titre-besoin">
                <h2 id="titre-besoin" class="titre-section">Le besoin</h2>
                @if ($projet['besoin'])
                    <p class="mt-4 max-w-3xl text-lg">{{ $projet->get('besoin.texte') }}</p>
                    <p class="mt-2 text-sm text-ardoise">{{ $format->source($projet->get('besoin.source')) }}</p>
                @else
                    <p class="mt-4 text-ardoise">Le besoin sera décrit à l’étape « étude en cours ».</p>
                @endif
            </section>

            @if ($projet['description_technique'])
                <section aria-labelledby="titre-description">
                    <h2 id="titre-description" class="titre-section">Description technique</h2>
                    <p class="mt-4 max-w-3xl whitespace-pre-line">{{ $projet['description_technique'] }}</p>
                </section>
            @endif

            @if ($projet['beneficiaires'])
                <section aria-labelledby="titre-beneficiaires">
                    <h2 id="titre-beneficiaires" class="titre-section">Bénéficiaires</h2>
                    <div class="mt-4 overflow-x-auto" tabindex="0" role="region" aria-label="Tableau des bénéficiaires">
                        <table class="tableau">
                            <thead><tr><th scope="col">Nombre</th><th scope="col">Catégorie</th><th scope="col">Méthode d’estimation</th><th scope="col">Nature et date</th></tr></thead>
                            <tbody>
                                @foreach ($projet['beneficiaires'] as $b)
                                    <tr><td class="font-bold">{{ $format->nombre($b['nombre']) }}</td><td>{{ $b['categorie'] }}</td><td>{{ $b['methode_estimation'] }}<span class="block text-xs text-ardoise">{{ $format->source($b['source']) }}</span></td>
                                        <td>{{ $format->nature($b['nature']) }} au {{ $format->date($b['date_estimation']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($projet['indicateurs'])
                <section aria-labelledby="titre-indicateurs">
                    <h2 id="titre-indicateurs" class="titre-section">Indicateurs de résultats</h2>
                    <div class="mt-4 overflow-x-auto" tabindex="0" role="region" aria-label="Tableau des indicateurs">
                        <table class="tableau">
                            <thead><tr><th scope="col">Indicateur</th><th scope="col">Référence</th><th scope="col">Cible</th><th scope="col">Atteint</th><th scope="col">Méthode de mesure</th></tr></thead>
                            <tbody>
                                @foreach ($projet['indicateurs'] as $i)
                                    <tr><td class="font-semibold">{{ $i['libelle'] }}</td><td>{{ $format->nombre($i['valeur_reference']) }} {{ $i['unite'] }}</td>
                                        <td>{{ $format->nombre($i['cible']) }} {{ $i['unite'] }}</td>
                                        <td>{{ $i['valeur_atteinte'] === null ? '—' : $format->nombre($i['valeur_atteinte']).' '.$i['unite'].' ('.$format->date($i['date_mesure']).')' }}</td>
                                        <td>{{ $i['methode_mesure'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($projet['pieces'])
                <section aria-labelledby="titre-pieces">
                    <h2 id="titre-pieces" class="titre-section">Études, autorisations et pièces</h2>
                    <ul class="mt-4 space-y-3">
                        @foreach ($projet['pieces'] as $piece)
                            <li class="flex items-start gap-3 rounded-carte bg-sable p-4"><x-icone nom="document" class="icone mt-0.5 text-lagune" />
                                <span><strong class="block">{{ $pieces[$piece['type_piece']] ?? $piece['type_piece'] }}</strong>{{ $piece['intitule'] }}, {{ $format->date($piece['date']) }}
                                    <span class="block text-sm text-ardoise">{{ $piece['diffusion'] === 'public' ? 'Consultable sur demande' : 'Document interne' }}</span></span></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside class="space-y-6 lg:sticky lg:top-6 lg:self-start">
            <x-portail.bloc-financier :projet="$projet" />
            <section class="rounded-carte bg-sable p-6" aria-labelledby="titre-en-bref">
                <h2 id="titre-en-bref" class="text-xl">En bref</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="font-bold text-ardoise">Localités</dt><dd>
                        @foreach ($projet['localites'] as $localite)
                            <a href="{{ route('decouvrir.localite', $localite['slug']) }}">{{ $localite['nom'] }}</a>@if (! $loop->last), @endif
                        @endforeach
                    </dd></div>
                    @if ($projet['avancement'])
                        <div><dt class="font-bold text-ardoise">Avancement des travaux</dt><dd>{{ $format->nombre($projet->get('avancement.pourcentage')) }} % au {{ $format->date($projet->get('avancement.date')) }}
                            <span class="block text-ardoise">{{ $format->source($projet->get('avancement.source')) }}</span></dd></div>
                    @endif
                    <div><dt class="font-bold text-ardoise">Suivi municipal</dt><dd>{{ $projet['service_responsable'] ?: 'Service des projets' }}</dd></div>
                    <div><dt class="font-bold text-ardoise">Publication</dt><dd>Version {{ $projet->version }}, publiée le {{ $format->date($projet->publieLe) }} après validation</dd></div>
                </dl>
            </section>
        </aside>
    </div>
</x-portail.gabarit>
