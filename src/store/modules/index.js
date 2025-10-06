import { createStore } from 'vuex';
import auth from './auth';
import notes from './notes';
import progress from './progress';
import app from './app';

export default createStore({
  modules: {
    auth,
    notes,
    progress,
    app
  }
});