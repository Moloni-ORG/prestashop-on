import {LoadAddress} from '../enums/LoadAddress';
import {DocumentStatus} from '../enums/DocumentStatus';
import {Boolean} from '../enums/Boolean';

export default class Settings {
  constructor() {
    this.settingIdPrefix = 'MoloniSettings_';
  }

  startObservers() {
    // Holders
    this.$loadAddressHolder = $('#settings_form_loadAddress_row');
    this.$customLoadAddressHolder = $('#settings_form_custom_loadAddress_row');
    this.$sendByEmailHolder = $('#settings_form_sendByEmail_row');
    this.$billOfLadingHolder = $('#settings_form_billOfLading_row');

    // Fields
    this.$shippingInfo = $(`#${this.settingIdPrefix}shippingInformation`);
    this.$loadAddress = $(`#${this.settingIdPrefix}loadAddress`);
    this.$documentStatus = $(`#${this.settingIdPrefix}documentStatus`);
    this.$sendByEmail = $(`#${this.settingIdPrefix}sendByEmail`);
    this.$billOfLading = $(`#${this.settingIdPrefix}billOfLading`);

    // Actions
    this.$documentStatus
      .on('change', this.onDocumentStatusChange.bind(this))
      .trigger('change');
    this.$shippingInfo
      .on('change', this.onShippingInformationChange.bind(this))
      .trigger('change');
    this.$loadAddress
      .on('change', this.onAddressChange.bind(this))
      .trigger('change');
  }

  onDocumentStatusChange(event) {
    switch (parseInt(event.target.value, 10)) {
      case DocumentStatus.DRAFT:
        this.$sendByEmailHolder.slideUp(200);
        this.$billOfLadingHolder.slideUp(200);
        this.$sendByEmail.val(Boolean.NO);
        this.$billOfLading.val(Boolean.NO);

        break;
      case DocumentStatus.CLOSED:
        this.$sendByEmailHolder.slideDown(200);
        this.$billOfLadingHolder.slideDown(200);

        break;
      default:
        break;
    }
  }

  onShippingInformationChange(event) {
    if (parseInt(event.target.value, 10) > 0) {
      this.$loadAddressHolder.slideDown(200);
    } else {
      this.$loadAddressHolder.slideUp(200);
      this.$loadAddress
        .val(LoadAddress.MOLONI)
        .trigger('change');
    }
  }

  onAddressChange(event) {
    if (parseInt(event.target.value, 10) === LoadAddress.CUSTOM) {
      this.$customLoadAddressHolder.slideDown(200);
    } else {
      this.$customLoadAddressHolder.slideUp(200);
    }
  }
}
