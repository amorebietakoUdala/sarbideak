import {
   Controller
} from '@hotwired/stimulus';

import { Modal } from 'bootstrap';

export default class extends Controller {
   static targets = ['modal'];
   static values = {
   };

   connect() {
      console.log("Spinner controller");
   }

   showSpinner(event) {
      this.openModal(event);
   }

   openModal(event) {
      console.log(event);
      const modal = new Modal(this.modalTarget);
      modal.show();
  }

//   hideSpinner(event) {
//    const modal = new Modal(this.modalTarget);
//    modal.hide();
//   }
}