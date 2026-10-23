import * as bootstrap from 'bootstrap';

import './bootstrap';
import './confirmations';
import {initializeDynamicSearch} from './dynamic-search';

import Alpine from 'alpinejs';

window.Alpine = Alpine;
window.bootstrap = bootstrap;

Alpine.start();
initializeDynamicSearch();
