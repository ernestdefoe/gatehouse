import Model from 'flarum/common/Model';

/** A member Gatehouse held: waiting, declined or approved. */
export default class Applicant extends Model {
  status() {
    return Model.attribute<string>('status').call(this);
  }

  reason() {
    return Model.attribute<string | null>('reason').call(this);
  }

  email() {
    return Model.attribute<string | null>('email').call(this);
  }

  username() {
    return Model.attribute<string>('username').call(this);
  }

  displayName() {
    return Model.attribute<string>('displayName').call(this);
  }

  heldAt() {
    return Model.attribute<Date | null, string | null>('heldAt', Model.transformDate).call(this);
  }

  decidedAt() {
    return Model.attribute<Date | null, string | null>('decidedAt', Model.transformDate).call(this);
  }

  decidedBy() {
    return Model.attribute<string | null>('decidedBy').call(this);
  }
}
