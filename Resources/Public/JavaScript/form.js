/*
 * This file is developed by evoWeb.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */
/**
 * Module: @evoweb/ew-llxml2xliff/form.js
 */

import { html, render } from "lit-html";
import DocumentService from "@typo3/core/document-service.js";

class Form {
  constructor() {
    DocumentService.ready().then(() => { this.initializeFormElements() })
  }

  initializeFormElements() {
    let selectElements = Array.from(document.querySelectorAll('select#extension, select#file'));
    selectElements.forEach(selectElement => {
      selectElement.addEventListener('change', event => { this.selectElementChanged(event); });
    });

    let confirmButton = document.querySelector('.confirm');
    confirmButton?.addEventListener('click', event => { this.confirmButtonClicked(event); })
  }

  selectElementChanged(event) {
    const target = event.target;

    this.showIcon(target);
    this.addLoadSpinner(target);
    target.closest('form').submit();
  }

  confirmButtonClicked(event) {
    const target = event.target;

    this.addLoadSpinner(target);
  }

  addLoadSpinner(element) {
    const loader = element.closest('.row').querySelector('.loader');
    render(html`<typo3-backend-spinner size="large" variant="dark"></typo3-backend-spinner>`, loader);
  }

  showIcon(element) {
    const groupIconContainer = element.parentElement.querySelector('.input-group-icon');
    if (groupIconContainer !== null) {
      groupIconContainer.innerHTML = (element.options[element.selectedIndex].dataset.icon);
    }
  }
}

export default new Form();
