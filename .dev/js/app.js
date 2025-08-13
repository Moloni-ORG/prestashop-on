import Settings from './pages/settings';
import Orders from './pages/orders';
import Documents from './pages/documents';
import Tools from './pages/tools';
import Logs from './pages/logs';
import Login from './pages/login';
import PrestashopProducts from './pages/prestashopProducts';
import MoloniProducts from './pages/moloniProducts';

$(document).ready(() => {
  window.molonion = {};

  window.molonion.Settings = new Settings();
  window.molonion.Orders = new Orders();
  window.molonion.Documents = new Documents();
  window.molonion.Tools = new Tools();
  window.molonion.Logs = new Logs();
  window.molonion.Login = new Login();
  window.molonion.PrestashopProducts = new PrestashopProducts();
  window.molonion.MoloniProducts = new MoloniProducts();
});
