var __typeError = (msg) => {
  throw TypeError(msg);
};
var __accessCheck = (obj, member, msg) => member.has(obj) || __typeError("Cannot " + msg);
var __privateGet = (obj, member, getter) => (__accessCheck(obj, member, "read from private field"), getter ? getter.call(obj) : member.get(obj));
var __privateAdd = (obj, member, value) => member.has(obj) ? __typeError("Cannot add the same private member more than once") : member instanceof WeakSet ? member.add(obj) : member.set(obj, value);
var _instances;
class BosonWebComponentsSet {
  constructor() {
    __privateAdd(this, _instances, {});
  }
  get(id) {
    let ref = __privateGet(this, _instances)[id];
    if (ref instanceof WeakRef) {
      let component = ref.deref();
      if (component instanceof HTMLElement) {
        return component;
      }
      delete __privateGet(this, _instances)[id];
    }
  }
  detach(id) {
    if (!!__privateGet(this, _instances)[id]) {
      delete __privateGet(this, _instances)[id];
    }
  }
  attach(id, el) {
    if (!!__privateGet(this, _instances)[id] && __privateGet(this, _instances)[id].deref() === el) {
      return;
    }
    __privateGet(this, _instances)[id] = new WeakRef(el);
  }
}
_instances = new WeakMap();
window.boson = window.boson || {};
try {
  window.boson.components = window.boson.components || {};
  window.boson.components.instances = new BosonWebComponentsSet();
} catch (e) {
  console.error("Failed to initialize Web Components subsystem", e);
}
