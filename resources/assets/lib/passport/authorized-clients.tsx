/**
 *    Copyright (c) ppy Pty Ltd <contact@ppy.sh>.
 *
 *    This file is part of osu!web. osu!web is distributed with the hope of
 *    attracting more community contributions to the core ecosystem of osu!.
 *
 *    osu!web is free software: you can redistribute it and/or modify
 *    it under the terms of the Affero GNU General Public License version 3
 *    as published by the Free Software Foundation.
 *
 *    osu!web is distributed WITHOUT ANY WARRANTY; without even the implied
 *    warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *    See the GNU Affero General Public License for more details.
 *
 *    You should have received a copy of the GNU Affero General Public License
 *    along with osu!web.  If not, see <http://www.gnu.org/licenses/>.
 */

import { runInAction } from 'mobx';
import { observer } from 'mobx-react';
import core from 'osu-core-singleton';
import { Client } from 'passport/client';
import * as React from 'react';

const store = core.dataStore.clientStore;

@observer
export class AuthorizedClients extends React.Component {
  componentDidMount() {
    runInAction(() => store.fetchAll());
  }

  render() {
    const rows: JSX.Element[] = [];
    for (const client of store.clients.values()) {
      rows.push(this.renderClient(client));
    }

    return (
      <div className='authorized-clients'>
        {rows}
      </div>
    );
  }

  renderClient(client: Client) {
    return (
      <div className='authorized-client'>
        <div className='authorized-client__details'>
          <div className='authorized-client__name'>{client.name}</div>
          <span className='authorized-client__owner'>{`Owner: ${client.userId}`}</span>
          <div className='authorized-client__permissions'>{Array.from(client.scopes).join(', ')}</div>
          </div>
        <div className='authorized-client__actions'>
          { client.revoked ? (
            <div className='authorized-client__button'>Revoked</div>
          ) : (
            <button
              className='authorized-client__button authorized-client__button--cancel'
              data-client-id={client.id}
              onClick={this.revokeClicked}
            >
              Revoke
            </button>
          )}
        </div>
      </div>
    );
  }

  revokeClicked = (event: React.MouseEvent<HTMLElement>) => {
    if (!confirm('Revoke this token?')) { return; }

    const clientId = (event.target as HTMLElement).dataset.clientId;
    const client = store.clients.get(+(clientId || 0));
    if (client != null) {
      client.revoke();
    }
  }
}
