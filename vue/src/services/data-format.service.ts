import moment from "moment";

class DataformatService {
      formatPhoneNumber(phoneNumber: string): string {
            if (phoneNumber.length === 9) {
                  phoneNumber = phoneNumber.replace(/([0-9]{3})([0-9]{3})([0-9]{3})/, '$1 $2 $3');
            } else if (phoneNumber.length === 10) {
                  phoneNumber = phoneNumber.replace(/([0-9]{3})([0-9]{3})([0-9]{4})/, '$1 $2 $3');
            }
            return phoneNumber;
      }
      formatDate(date: string, format = setUpDefault.formatDate) {
            if (date) {
                  return moment(date).format(format)
            }
            return ""
      }
}

export default DataformatService


export const setUpDefault = {
      formatDate: "DD/MM/YYYY",
      formatTime: "HH:mm:ss",
      formatDateTime: "DD/MM/YYYY HH:mm",
      formatDateTimeComa: "DD/MM/YYYY, HH:mm",
      formatDateServer: "YYYYMMDD"
}