// Portail de Makary : amélioration progressive uniquement. Sans JavaScript, tout reste utilisable.

// Le menu mobile (élément <details>) se ferme avec Échap, où que soit le focus, et quand on choisit un lien.
const menus = document.querySelectorAll('details.menu-mobile');
document.addEventListener('keydown', (evenement) => {
    if (evenement.key !== 'Escape') return;
    for (const menu of menus) {
        if (!menu.open) continue;
        menu.open = false;
        menu.querySelector('summary')?.focus();
    }
});
for (const menu of menus) {
    menu.addEventListener('click', (evenement) => {
        if (evenement.target.closest('a')) menu.open = false;
    });
}

// Bouton « Copier le numéro » de l'accusé de réception, affiché seulement si le presse-papiers est disponible.
for (const bouton of document.querySelectorAll('[data-copier]')) {
    if (!navigator.clipboard) continue;
    bouton.hidden = false;
    bouton.addEventListener('click', async () => {
        await navigator.clipboard.writeText(bouton.dataset.copier);
        const etat = document.getElementById(bouton.getAttribute('aria-describedby'));
        if (etat) etat.textContent = 'Numéro copié.';
    });
}

// Récapitulatif des erreurs d'un formulaire : il reçoit le focus pour être annoncé.
document.querySelector('[data-erreurs]')?.focus();
