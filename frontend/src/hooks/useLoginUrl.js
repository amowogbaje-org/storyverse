import { useLocation } from "react-router-dom";
import { loginUrl } from "../utils/loginUrl";

/** /login URL that returns to the current page after signing in. */
export function useLoginUrl() {
  const location = useLocation();
  return loginUrl(`${location.pathname}${location.search}`);
}
