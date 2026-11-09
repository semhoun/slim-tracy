import { setupDevtoolsPlugin } from '@vue/devtools-api';

const registrationKey = Symbol.for('slim-tracy.vue-devtools.v1');
const pluginId = 'slim-tracy.ajax-profiler';
const layerId = pluginId + '.requests';
const inspectorId = pluginId + '.profiles';

// The v6 public API has no removeTimelineLayer/removeInspector or hook unsubscribe.
// Reuse one registration per app; stopped and late callbacks are inert instead.
export function installVueDevtools(app, client) {
    if (!app || !client?.active) return { stop() {} };
    const registrations = globalThis[registrationKey] ||= new WeakMap();
    let record = registrations.get(app);
    if (record?.handle) return record.handle;
    if (!record) {
        record = { api: null, entries: [], active: false, handle: null };
        registrations.set(app, record);
    }
    record.active = true;
    record.client = client;
    record.entries = client.getEntries().slice(-100);
    const refresh = () => {
        if (!record.active || !record.api) return;
        record.api.sendInspectorTree(inspectorId);
        record.api.sendInspectorState(inspectorId);
    };
    const emit = (entry) => {
        if (!record.active || !record.api) return;
        record.api.addTimelineEvent({ layerId, event: {
            time: record.api.now(), title: entry.method + ' ' + entry.path,
            subtitle: entry.outcome, data: entry, groupId: String(entry.localId),
        } });
    };
    const unsubscribe = client.subscribe((entry) => {
        record.entries.push(entry);
        if (record.entries.length > 100) record.entries.shift();
        emit(entry);
        refresh();
    });
    const handle = { stop() {
        if (record.handle !== handle) return;
        record.active = false;
        record.handle = null;
        record.entries = [];
        record.client = null;
        unsubscribe();
    } };
    record.handle = handle;
    if (!record.registered) {
        record.registered = true;
        record.connect = () => {
            if (!record.active || record.initialized || !record.api) return;
            record.initialized = true;
            const api = record.api;
            api.addTimelineLayer({ id: layerId, label: 'Slim Tracy API', color: 0x197d87 });
            api.addInspector({ id: inspectorId, label: 'Slim Tracy API', icon: 'http' });
            api.on.getInspectorTree((payload) => {
                if (payload.app !== app || payload.inspectorId !== inspectorId) return;
                payload.rootNodes = record.active ? record.entries
                    .filter((entry) => (entry.method + ' ' + entry.path).includes(payload.filter || ''))
                    .map((entry) => ({ id: String(entry.localId), label: entry.method + ' ' + entry.path })) : [];
            });
            api.on.getInspectorState(async (payload) => {
                if (payload.app !== app || payload.inspectorId !== inspectorId) return;
                const entry = record.active && record.entries.find((item) => String(item.localId) === payload.nodeId);
                payload.state = entry ? { 'Client-Reported Request': Object.entries(entry)
                    .map(([key, value]) => ({ key, value, editable: false })) } : {};
                if (entry?.id && record.client?.getProfile) {
                    const handle = record.handle;
                    const profile = await record.client.getProfile(entry.id);
                    if (!record.active || record.handle !== handle) {
                        payload.state = {};
                        return;
                    }
                    if (profile) {
                        payload.state['Server Profile'] = Object.entries(profile)
                            .map(([key, value]) => ({ key, value, editable: false }));
                    }
                }
            });
        };
        setupDevtoolsPlugin({ id: pluginId, label: 'Slim Tracy API', packageName: '@slim-tracy/ajax-profiler',
            homepage: 'https://github.com/semhoun/slim-tracy', app }, (api) => {
            record.api = api;
            if (!record.active) return;
            record.connect();
            record.entries.forEach(emit);
            refresh();
        });
    } else if (record.api) {
        record.connect();
        record.entries.forEach(emit);
        refresh();
    }
    return handle;
}
