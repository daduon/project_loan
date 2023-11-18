<template src="./SummaryLoan002.html"></template>

<script lang="ts">
import { defineComponent } from "vue";
import Client from "../../../services/client";

export default defineComponent({
    name: "SummaryLoan002",
    data() {
        const scheduleDetail = [] as any;
        return {
            scheduleHeader: {},
            scheduleDetail,
            companyName: "",
            companyImage: "",
        };
    },
    mounted() {
        // console.log("hello");
        Client.get("/CompanyProfile/1").then((res) => {
            this.companyName = res.data.company_profile_name;
            this.companyImage = res.data.company_profile_image;
            localStorage.setItem("companyName", this.companyName);
            localStorage.setItem("companyImage", this.companyImage);
        });

        Client.get(
            `/getsummaryprint/${localStorage.getItem(
                "_id"
            )}/${localStorage.getItem("_loandId")}`
        ).then((res) => {
            // console.log(res.data);
            this.scheduleHeader = res.data.header;
            this.scheduleDetail = res.data.schedules;
            setTimeout(() => {
                window.print();
                window.close();
            }, 1000);
        });
    },
    methods: {
        CheckPayType(payType: string) {
            if (payType === "01") {
                return "Monthly";
            } else if (payType === "02") {
                return "3 Monthly";
            } else if (payType === "03") {
                return "6 Monthly";
            } else if (payType === "04") {
                return "Yearly";
            } else if (payType === "05") {
                return "At Maturity";
            } else if (payType === "15") {
                return "Half Month";
            }
        },
        FormatDate(date: string = "2023-12-12") {
            // Split the date string into year, month, and day.
            const [year, month, day] = date.split("-");

            // Return the date in the desired format.
            return `${day}-${month}-${year}`;
        },
    },
});
</script>
