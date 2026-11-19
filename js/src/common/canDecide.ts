import app from 'flarum/common/app';

/** Whether the viewer may approve and decline: the server sends the waiting count only to them. */
export default function canDecide(): boolean {
  return app.forum.attribute<number | undefined>('gatehousePending') != null;
}
