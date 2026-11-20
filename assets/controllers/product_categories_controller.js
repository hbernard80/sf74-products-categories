import { Controller } from '@hotwired/stimulus';

/**
 * Peuple la liste des sous-catégories selon la catégorie parente choisie,
 * sans perdre les sous-catégories déjà sélectionnées sous un autre parent.
 */
export default class extends Controller {
    static values = {
        url: String,
        subcategoriesField: String,
    };

    async update() {
        const subcategoriesField = document.getElementById(this.subcategoriesFieldValue);

        if (!subcategoriesField) {
            return;
        }

        // On ne retire que les options non sélectionnées : les choix déjà faits restent.
        for (const option of [...subcategoriesField.options]) {
            if (!option.selected) {
                option.remove();
            }
        }

        if (!this.element.value) {
            return;
        }

        const response = await fetch(`${this.urlValue}?parent=${encodeURIComponent(this.element.value)}`, {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            return;
        }

        const categories = await response.json();
        const parentName = this.element.selectedOptions[0]?.text ?? '';
        const existingIds = new Set([...subcategoriesField.options].map((option) => option.value));

        for (const category of categories) {
            if (!existingIds.has(String(category.id))) {
                subcategoriesField.add(new Option(`${parentName} › ${category.name}`, category.id));
            }
        }
    }
}
