import { Controller } from '@hotwired/stimulus';

import { fetchResponse, doFetch } from '../modules/fetchResponse';

export default class extends Controller {
   static targets = ['toggleOn', 'toggleOff'];
   static values = {
   };

   async onUnlock(e) {
      const response = await this.doFetch(e);
   }

   async onActivateOfficeMode(e) {
      const response = await this.doFetch(e);
      if (response.status == 'success') {
         this.toggleOnTarget.classList.remove('d-none');
         this.toggleOffTarget.classList.add('d-none');
      }
   }

   async onDeactivateOfficeMode(e) {
      const response = await this.doFetch(e);
      if (response.status == 'success') {
         this.toggleOnTarget.classList.add('d-none');
         this.toggleOffTarget.classList.remove('d-none');
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
