<template>
  <div>
    <h1>Gestor WiFi</h1>

    <div>
      <label>API Key (opcional):
        <input v-model="apiKey" type="password" placeholder="X-API-Key" />
      </label>
    </div>

    <div>
      <label>CBI:
        <input v-model="cbi" type="text" placeholder="Ej: 16550441187" />
      </label>
    </div>

    <div>
      <button @click="loadNetworks">Cargar redes (GET)</button>
      <button @click="saveNetworks">Guardar redes (PUT por CBI)</button>
      <button @click="saveNetworksAll">Guardar (todos)</button>
      <label><input type="checkbox" v-model="replace" /> replace=true</label>
    </div>

    <table border="1" cellpadding="4" cellspacing="0">
      <thead>
        <tr>
          <th>SSID</th>
          <th>Password</th>
          <th>Prioridad</th>
          <th>Habilitada</th>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(n, i) in networks" :key="i">
          <td><input v-model="n.ssid" /></td>
          <td><input v-model="n.password" /></td>
          <td><input type="number" v-model.number="n.priority" /></td>
          <td><input type="checkbox" v-model="n.enabled" /></td>
          <td><button @click="removeRow(i)">Eliminar</button></td>
        </tr>
      </tbody>
    </table>
    <button @click="addRow">Agregar red</button>

    <pre>{{ status }}</pre>
  </div>
</template>

<script>
export default {
  name: 'WifiManager',
  data() {
    return {
      apiKey: '',
      cbi: '',
      replace: true,
      networks: [{ ssid: '', password: '', priority: 0, enabled: true }],
      status: ''
    };
  },
  methods: {
    headers() {
      const h = { 'Content-Type': 'application/json' };
      if (this.apiKey?.trim()) h['X-API-Key'] = this.apiKey.trim();
      return h;
    },
    setStatus(msg) { this.status = String(msg || ''); },
    addRow() { this.networks.push({ ssid: '', password: '', priority: 0, enabled: true }); },
    removeRow(i) { this.networks.splice(i, 1); },
    async loadNetworks() {
      this.setStatus('');
      if (!this.cbi.trim()) return this.setStatus('Ingresa un CBI.');
      try {
        const res = await fetch(`/api/wifi/get?cbi=${encodeURIComponent(this.cbi.trim())}`, { headers: this.headers() });
        const text = await res.text();
        if (!res.ok) return this.setStatus(`Error ${res.status}: ${text}`);
        const data = JSON.parse(text || '{}');
        this.networks = (data.networks || []).length ? data.networks : [{ ssid: '', password: '', priority: 0, enabled: true }];
        this.setStatus(`Cargado OK. Versión: ${data.version ?? 0}`);
      } catch (e) {
        this.setStatus('Fallo al cargar: ' + (e?.message || e));
      }
    },
    async saveNetworks() {
      this.setStatus('');
      if (!this.cbi.trim()) return this.setStatus('Ingresa un CBI.');
      const nets = this.networks.filter(n => (n.ssid || '').trim());
      if (!nets.length) return this.setStatus('Ingresa al menos un SSID.');
      try {
        const res = await fetch('/api/wifi/put', {
          method: 'POST', headers: this.headers(),
          body: JSON.stringify({ CBI: this.cbi.trim(), networks: nets, replace: !!this.replace })
        });
        const text = await res.text();
        if (!res.ok) return this.setStatus(`Error ${res.status}: ${text}`);
        const data = JSON.parse(text || '{}');
        this.setStatus(`Guardado OK. Nueva versión: ${data.version ?? '—'}`);
      } catch (e) {
        this.setStatus('Fallo al guardar: ' + (e?.message || e));
      }
    },
    async saveNetworksAll() {
      this.setStatus('');
      const nets = this.networks.filter(n => (n.ssid || '').trim());
      if (!nets.length) return this.setStatus('Ingresa al menos un SSID.');
      try {
        const res = await fetch('/api/wifi/put_all', {
          method: 'POST', headers: this.headers(),
          body: JSON.stringify({ networks: nets, replace: !!this.replace })
        });
        const text = await res.text();
        if (!res.ok) return this.setStatus(`Error ${res.status}: ${text}`);
        const data = JSON.parse(text || '{}');
        this.setStatus(`Broadcast OK. Versión global: ${data.version ?? '—'}`);
      } catch (e) {
        this.setStatus('Fallo broadcast: ' + (e?.message || e));
      }
    }
  }
}
</script>