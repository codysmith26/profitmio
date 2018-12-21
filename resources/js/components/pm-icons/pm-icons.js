// Add all global icons here
import Vue from 'vue';
import {DownloadIcon, MenuIcon, PlusIcon, TrashIcon, XIcon} from 'vue-feather-icons';

require('./pm-icons.scss');

Vue.component('download-icon', DownloadIcon);
Vue.component('menu-icon', MenuIcon);
Vue.component('plus-icon', PlusIcon);
Vue.component('x-icon', XIcon);
Vue.component('trash-icon', TrashIcon);
