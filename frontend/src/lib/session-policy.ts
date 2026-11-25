/** Non-secret browser login generation. Never store a token or user profile here. */
export const SESSION_GENERATION='simrs-browser-login-generation';
export const LOGGED_OUT='simrs-browser-signed-out';
/** A fresh tab may restore the shared cookie; a tab bound to an older login must reauthenticate. */
export function canRestoreGeneration(tab:string|null,shared:string|null):boolean {
 return tab===null||tab===shared;
}
/** Only the current generation may suppress restore/revoke the shared browser cookie. */
export function ownsGeneration(tab:string|null,shared:string|null):boolean {return tab===shared;}
