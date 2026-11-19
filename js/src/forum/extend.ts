import Extend from 'flarum/common/extenders';
import commonExtend from '../common/extend';
import ApplicantNotification from './components/ApplicantNotification';
import ApplicantsPage from './components/ApplicantsPage';

export default [
  ...commonExtend,

  /*
   * Bundled, not lazy: measured, webpack's chunk loader (~4 KB) costs every
   * visitor more than the page itself does (forum.js 2.23 KB gz bundled vs
   * 2.59 KB gz with the page split out).
   */
  new Extend.Routes().add('gatehouse.applicants', '/gatehouse', ApplicantsPage),

  new Extend.Notification().add('gatehouseApplicant', ApplicantNotification),
];
