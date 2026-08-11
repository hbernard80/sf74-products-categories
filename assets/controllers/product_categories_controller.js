import { Controller } from '@hotwired/stimulus';

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

        subcategoriesField.innerHTML = '';

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

        for (const category of categories) {
            subcategoriesField.add(new Option(category.name, category.id));
        }
    }
}
