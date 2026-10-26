@php($estEdition = $mode === 'edition')
<x-admin.gabarit :titre="$estEdition ? 'Modifier l’utilisateur' : 'Nouvel utilisateur'"
                 chapeau="Définissez les informations de connexion, la fonction et le rôle attribué pour cet agent.">
    <p class="-mt-4 mb-6 text-sm">
        <a href="{{ route('admin.utilisateurs.index') }}" class="text-lac font-semibold">← Retour à la liste des utilisateurs</a>
    </p>

    <div class="max-w-2xl">
        <form method="post" action="{{ $estEdition ? route('admin.utilisateurs.mettre-a-jour', $utilisateur) : route('admin.utilisateurs.enregistrer') }}"
              class="panneau space-y-6" novalidate>
            @csrf
            @if ($estEdition)
                @method('PUT')
            @endif

            <div class="space-y-4">
                <h2 class="text-lg font-bold text-nuit border-b border-argile pb-2">Identité et contact</h2>

                <div>
                    <label for="champ-nom_affiche" class="etiquette">Nom complet <span class="text-rose-600 font-bold">*</span></label>
                    <input id="champ-nom_affiche" type="text" name="nom_affiche"
                           value="{{ old('nom_affiche', $utilisateur->nom_affiche) }}"
                           placeholder="Ex : Mahamat Abba" required maxlength="120"
                           class="champ @error('nom_affiche') border-rose-500 @enderror" />
                    @error('nom_affiche')
                        <p class="erreur mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="champ-courriel" class="etiquette">Adresse électronique professionnelle <span class="text-rose-600 font-bold">*</span></label>
                    <input id="champ-courriel" type="email" name="courriel"
                           value="{{ old('courriel', $utilisateur->courriel) }}"
                           placeholder="Ex : mahamat.abba@makary.cm" required maxlength="120"
                           class="champ @error('courriel') border-rose-500 @enderror" />
                    <p class="aide text-xs text-ardoise mt-1">Sert d'identifiant de connexion au back-office.</p>
                    @error('courriel')
                        <p class="erreur mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="champ-fonction" class="etiquette">Fonction / Titre</label>
                    <input id="champ-fonction" type="text" name="fonction"
                           value="{{ old('fonction', $utilisateur->fonction) }}"
                           placeholder="Ex : Secrétaire Général de la Commune" maxlength="120"
                           class="champ @error('fonction') border-rose-500 @enderror" />
                    @error('fonction')
                        <p class="erreur mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="space-y-4 pt-4 border-t border-argile">
                <h2 class="text-lg font-bold text-nuit border-b border-argile pb-2">Attribution du rôle</h2>

                <div>
                    <label for="champ-role_code" class="etiquette">Rôle d'administration <span class="text-rose-600 font-bold">*</span></label>
                    <select id="champ-role_code" name="role_code" required
                            class="champ @error('role_code') border-rose-500 @enderror">
                        <option value="">Sélectionnez un rôle…</option>
                        @foreach ($roles as $r)
                            <option value="{{ $r->code }}" @selected(old('role_code', $roleActuel) === $r->code)>
                                {{ $r->libelle }} ({{ $r->code === 'superadmin' ? 'Administration intégrale de toute la plateforme' : 'Gestion globale, validation, utilisateurs et coordination' }})
                            </option>
                        @endforeach
                    </select>
                    <p class="aide text-xs text-ardoise mt-1">Donne accès à la gestion des utilisateurs, aux contenus, projets, documents et à la validation.</p>
                    @error('role_code')
                        <p class="erreur mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="space-y-4 pt-4 border-t border-argile">
                <h2 class="text-lg font-bold text-nuit border-b border-argile pb-2">Sécurité et mot de passe</h2>

                <div>
                    <label for="champ-mot_de_passe" class="etiquette">
                        Mot de passe @if (! $estEdition)<span class="text-rose-600 font-bold">*</span>@else <span class="text-ardoise font-normal text-xs">(laisser vide pour ne pas modifier)</span>@endif
                    </label>
                    <input id="champ-mot_de_passe" type="password" name="mot_de_passe"
                           autocomplete="new-password" @required(! $estEdition) minlength="8"
                           class="champ @error('mot_de_passe') border-rose-500 @enderror" />
                    <p class="aide text-xs text-ardoise mt-1">Au moins 8 caractères (lettres, chiffres et symboles recommandés).</p>
                    @error('mot_de_passe')
                        <p class="erreur mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="champ-mot_de_passe_confirmation" class="etiquette">
                        Confirmer le mot de passe @if (! $estEdition)<span class="text-rose-600 font-bold">*</span>@endif
                    </label>
                    <input id="champ-mot_de_passe_confirmation" type="password" name="mot_de_passe_confirmation"
                           autocomplete="new-password" @required(! $estEdition) minlength="8"
                           class="champ" />
                </div>
            </div>

            <div class="space-y-4 pt-4 border-t border-argile">
                <div class="flex items-center gap-3">
                    <input type="hidden" name="actif" value="0">
                    <input id="champ-actif" type="checkbox" name="actif" value="1"
                           class="case" @checked(old('actif', $utilisateur->actif))>
                    <label for="champ-actif" class="font-bold text-nuit cursor-pointer">Compte actif (autorisé à se connecter au back-office)</label>
                </div>
                @error('actif')
                    <p class="erreur mt-1 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between gap-4 pt-6 border-t border-argile">
                <a href="{{ route('admin.utilisateurs.index') }}" class="bouton bouton-secondaire">
                    Annuler
                </a>
                <button type="submit" class="bouton bouton-principal">
                    {{ $estEdition ? 'Enregistrer les modifications' : 'Créer l’utilisateur' }}
                </button>
            </div>
        </form>

        @if ($estEdition && $utilisateur->id !== auth()->id())
            <div class="mt-8 panneau border border-rose-200 bg-rose-50/50 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-sm font-bold text-rose-800">Zone de danger</h3>
                    <p class="text-xs text-rose-700 mt-0.5">Supprimer définitivement ce compte utilisateur et révoquer tous ses accès.</p>
                </div>
                <form method="post" action="{{ route('admin.utilisateurs.detruire', $utilisateur) }}"
                      onsubmit="return confirm('Êtes-vous certain de vouloir supprimer définitivement cet utilisateur ? Cette action est irréversible.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bouton text-xs bg-rose-600 hover:bg-rose-700 text-white font-bold px-4 py-2 rounded-lg">
                        Supprimer le compte
                    </button>
                </form>
            </div>
        @endif
    </div>
</x-admin.gabarit>
