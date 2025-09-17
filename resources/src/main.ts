
import {type BosonWebComponents} from "./components/BosonWebComponents";
import BosonWebComponentsSet from "./components/BosonWebComponentsSet";

declare const window: {
    boson: {
        components: BosonWebComponents,
    },
};

/**
 * Prepare public accessor instance.
 */
window.boson = window.boson || {};

/**
 * Initialize Web Components subsystem
 */
try {
    window.boson.components = window.boson.components || {};

    window.boson.components.instances = new BosonWebComponentsSet();
} catch (e) {
    console.error('Failed to initialize Web Components subsystem', e);
}
