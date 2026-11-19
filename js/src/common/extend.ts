import Extend from 'flarum/common/extenders';
import Applicant from './models/Applicant';

export default [new Extend.Store().add('gatehouse-applicants', Applicant)];
