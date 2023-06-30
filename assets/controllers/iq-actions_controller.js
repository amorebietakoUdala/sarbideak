import { Controller } from '@hotwired/stimulus';

import { Modal } from 'bootstrap';

import { fetchResponse } from '../modules/fetchResponse';

export default class extends Controller {
   static targets = ['restoreButton'];
   static values = {
   };

   async onShow(e) {
      const response = await this.doFetch(e);
   }

   async onRestore(event) {
      event.preventDefault();
      const response = await this.doFetch(e);
      if (response.status == 'success') {
         this.restoreButtonTarget.classList.add('d-none');
      }
   }

   async doFetch(e) {
      e.preventDefault();
      this.dispatch('startAction');
      const url = e.currentTarget.href;
      const response = await fetchResponse(url);
      this.dispatch('endAction', {
         detail: response,
      });
      return response;
   }
}