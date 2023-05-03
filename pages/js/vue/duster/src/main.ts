import { createApp } from 'vue'
import PrimeVue from 'primevue/config';
import Badge from 'primevue/badge'

import Button from 'primevue/button'
import Card from 'primevue/card'
import Checkbox from 'primevue/checkbox'
import Toast from 'primevue/toast';
import ToastService from 'primevue/toastservice';
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import InputNumber from 'primevue/inputnumber'
import InputSwitch from 'primevue/inputswitch';
import InputText from 'primevue/inputtext'
import Panel from "primevue/panel";
import RadioButton from 'primevue/radiobutton'
import Divider from 'primevue/divider';
import Tag from 'primevue/tag'

import Toolbar from 'primevue/toolbar'
import Tooltip from 'primevue/tooltip'

import App from './App.vue'
import 'primeflex/primeflex.css'

// import "primevue/resources/themes/bootstrap4-light-blue/theme.css";
import '@/assets/themes/stanford/theme.scss'
import 'primevue/resources/primevue.min.css';
import 'primeicons/primeicons.css'
import {defineRule} from "vee-validate";
// import './assets/main.css'

const app = createApp(App)
    .use(PrimeVue)
    .use(ToastService)
    .component( 'Badge', Badge)
    .component( 'Button', Button)
    .component( 'Card', Card)
    .component( 'Checkbox', Checkbox)
    .component( 'Column', Column)
    .component( 'DataTable', DataTable)
    .component( 'Dialog', Dialog)
    .component( 'Dropdown', Dropdown)
    .component( 'InputNumber', InputNumber)
    .component( 'InputSwitch', InputSwitch)
    .component( 'InputText', InputText)
    .component( 'Panel', Panel)
    .component( 'RadioButton', RadioButton)
    .component( 'Divider', Divider)

    .component( 'Tag', Tag)
    .component( 'Toolbar', Toolbar)
    .directive('tooltip', Tooltip)
    .mount('#app')

